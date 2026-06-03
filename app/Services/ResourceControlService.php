<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use App\Models\Package;

/**
 * Applies open-source resource controls per user:
 * - cgroups v2 CPU/memory limits
 * - nproc/nofile limits via /etc/security/limits.d
 * - disk quotas via XFS project quotas (if supported)
 * - PHP-FPM pool tuning from package settings
 */
class ResourceControlService
{
    /**
     * Apply all resource limits for a user based on their package.
     */
    public static function applyForUser(string $username, ?string $packageName = null): array
    {
        $package = $packageName ? Package::where('name', $packageName)->first() : null;
        $actions = [];
        $errors = [];

        // 1. Process/file limits (always applied — safe defaults even without package)
        try {
            $nproc = $package?->nproc ?? 100;
            $nofile = $package?->nofile ?? 256;
            self::setLimitsConfig($username, (int) $nproc, (int) $nofile);
            $actions[] = "limits_set_nproc_{$nproc}_nofile_{$nofile}";
        } catch (\Throwable $e) {
            $errors[] = 'limits: ' . $e->getMessage();
        }

        // 2. cgroups v2 limits (if cgroups v2 available and package specifies)
        try {
            if ($package && self::isCgroupsV2Available()) {
                if (!empty($package->cgroups)) {
                    self::applyCgroupLimits($username, $package);
                    $actions[] = 'cgroups_applied';
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'cgroups: ' . $e->getMessage();
        }

        // 3. Disk quota (if XFS with project quota support)
        try {
            if ($package && $package->disk_space_mb > 0 && self::isQuotaSupported()) {
                self::setDiskQuota($username, (int) $package->disk_space_mb);
                $actions[] = "quota_set_{$package->disk_space_mb}MB";
            }
        } catch (\Throwable $e) {
            $errors[] = 'quota: ' . $e->getMessage();
        }

        return [
            'username' => $username,
            'package' => $packageName ?? 'default',
            'actions' => $actions,
            'errors' => $errors,
        ];
    }

    /**
     * Remove all resource limits for a user (cleanup on account deletion).
     */
    public static function removeFromUser(string $username): array
    {
        $actions = [];

        // Remove limits.d config
        $limitsFile = "/etc/security/limits.d/90-{$username}.conf";
        if (file_exists($limitsFile)) {
            @unlink($limitsFile);
            $actions[] = 'limits_removed';
        }

        // Remove cgroup config
        $cgroupDir = "/sys/fs/cgroup/user.slice/user-{$username}";
        if (is_dir($cgroupDir)) {
            Process::run("cgdelete -g cpu,memory:/openpanel_{$username} 2>/dev/null || true");
            $actions[] = 'cgroup_removed';
        }

        // Remove disk quota
        if (self::isQuotaSupported()) {
            Process::run("xfs_quota -x -c 'limit -p bsoft=0 bhard=0 " . escapeshellarg(self::projectName($username)) . "' /home 2>/dev/null || true");
            $actions[] = 'quota_removed';
        }

        return [
            'username' => $username,
            'actions' => $actions,
        ];
    }

    /**
     * Get current resource usage for a user.
     */
    public static function getUsage(string $username): array
    {
        $usage = [
            'disk_mb' => self::getDiskUsageMb($username),
            'processes' => self::getProcessCount($username),
            'memory_mb' => self::getMemoryUsageMb($username),
        ];

        if (self::isQuotaSupported()) {
            $usage['quota_mb'] = self::getDiskQuotaMb($username);
        }

        return $usage;
    }

    // =========================================================================
    // limits.d — nproc/nofile
    // =========================================================================

    protected static function setLimitsConfig(string $username, int $nproc, int $nofile): void
    {
        $conf = <<<CONF
# OpenPanel resource limits for {$username}
{$username}  soft  nproc   {$nproc}
{$username}  hard  nproc   {$nproc}
{$username}  soft  nofile  {$nofile}
{$username}  hard  nofile  {$nofile}
{$username}  hard  core    0
CONF;

        // Write via temp file + sudo cp (API runs as unprivileged user)
        $tmp = tempnam(sys_get_temp_dir(), 'limits');
        file_put_contents($tmp, $conf);
        Process::run("sudo cp " . escapeshellarg($tmp) . " /etc/security/limits.d/90-{$username}.conf");
        @unlink($tmp);
    }

    // =========================================================================
    // cgroups v2 — CPU and memory limits
    // =========================================================================

    protected static function applyCgroupLimits(string $username, Package $package): void
    {
        $cgroupName = "openpanel_{$username}";

        // Create cgroup
        Process::run("mkdir -p /sys/fs/cgroup/{$cgroupName} 2>/dev/null || true");

        // Parse cgroups field: "cpu=50000 memory=512M" format
        $limits = self::parseCgroupConfig($package->cgroups);

        // CPU weight/quot a (cgroups v2)
        if (isset($limits['cpu'])) {
            $cpuVal = (int) $limits['cpu'];
            // cpu.max format: "$MAX $PERIOD" (microseconds)
            // e.g., 50000 out of 100000 = 50% CPU
            Process::run("echo '{$cpuVal} 100000' > /sys/fs/cgroup/{$cgroupName}/cpu.max 2>/dev/null || true");
        }

        // Memory limit
        if (isset($limits['memory'])) {
            $memVal = $limits['memory'];
            Process::run("echo '{$memVal}' > /sys/fs/cgroup/{$cgroupName}/memory.max 2>/dev/null || true");
        }

        // Set process limits in cgroup
        if ($package->nproc > 0) {
            Process::run("echo '{$package->nproc}' > /sys/fs/cgroup/{$cgroupName}/pids.max 2>/dev/null || true");
        }
    }

    protected static function parseCgroupConfig(string $config): array
    {
        $result = [];
        // Format: "cpu=50000 memory=512M" or JSON
        if (str_starts_with($config, '{')) {
            return json_decode($config, true) ?? [];
        }
        foreach (preg_split('/\s+/', trim($config)) as $pair) {
            [$key, $val] = explode('=', $pair, 2) + [null, null];
            if ($key && $val) {
                $result[$key] = $val;
            }
        }
        return $result;
    }

    // =========================================================================
    // XFS disk quotas (project-based)
    // =========================================================================

    protected static function setDiskQuota(string $username, int $mb): void
    {
        $project = self::projectName($username);
        $home = "/home/{$username}";
        $quotaFile = "/etc/projects/{$project}";
        $mapFile = "/etc/projid/{$project}";

        // Register project (use sudo for privileged dirs)
        Process::run("sudo mkdir -p /etc/projects /etc/projid 2>/dev/null || true");

        // Find a unique project ID (hash of username)
        $projId = 10000 + (crc32($username) % 50000);

        $tmpMap = tempnam(sys_get_temp_dir(), 'qm');
        file_put_contents($tmpMap, "{$project}:{$projId}\n");
        Process::run("sudo cp " . escapeshellarg($tmpMap) . " " . escapeshellarg($mapFile));
        @unlink($tmpMap);

        $tmpProj = tempnam(sys_get_temp_dir(), 'qp');
        file_put_contents($tmpProj, "{$projId}:{$home}\n");
        Process::run("sudo cp " . escapeshellarg($tmpProj) . " " . escapeshellarg($quotaFile));
        @unlink($tmpProj);

        // Apply quota (blocks: 1 block = 1KB on XFS)
        $blocks = $mb * 1024;
        Process::run("sudo xfs_quota -x -c 'project -s {$project}' /home 2>/dev/null || true");
        Process::run("sudo xfs_quota -x -c 'limit -p bsoft={$blocks}k bhard={$blocks}k {$project}' /home 2>/dev/null || true");
    }

    protected static function getDiskQuotaMb(string $username): int
    {
        $project = self::projectName($username);
        $result = Process::run("xfs_quota -x -c 'quota -p -b " . escapeshellarg($project) . "' /home 2>/dev/null");
        // Parse output: "project  10123  500000  550000  00   0"
        if (preg_match('/\s+(\d+)\s+(\d+)/', $result->output(), $m)) {
            return (int) ((int) $m[1] / 1024); // KB to MB
        }
        return 0;
    }

    protected static function projectName(string $username): string
    {
        return "openpanel_{$username}";
    }

    // =========================================================================
    // Disk/process/memory usage helpers
    // =========================================================================

    protected static function getDiskUsageMb(string $username): int
    {
        $result = Process::run("du -sm /home/{$username} 2>/dev/null | awk '{print $1}'");
        return (int) trim($result->output());
    }

    protected static function getProcessCount(string $username): int
    {
        $result = Process::run("ps -u " . escapeshellarg($username) . " --no-headers 2>/dev/null | wc -l");
        return (int) trim($result->output());
    }

    protected static function getMemoryUsageMb(string $username): int
    {
        // Sum RSS of all user processes in KB, convert to MB
        $result = Process::run("ps -u " . escapeshellarg($username) . " -o rss= 2>/dev/null | awk '{s+=$1} END {print int(s/1024)}'");
        return (int) trim($result->output());
    }

    // =========================================================================
    // Capability checks
    // =========================================================================

    protected static function isCgroupsV2Available(): bool
    {
        return is_dir('/sys/fs/cgroup/system.slice') && file_exists('/sys/fs/cgroup/cgroup.controllers');
    }

    protected static function isQuotaSupported(): bool
    {
        // Check if /home is XFS with project quota
        $result = Process::run("xfs_info /home 2>/dev/null | grep -c 'pquota'");
        return (int) trim($result->output()) > 0;
    }
}
