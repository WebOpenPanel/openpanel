<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;

class AbuseMonitorService
{
    /**
     * Run all abuse checks and return findings.
     */
    public static function scan(): array
    {
        $findings = [];

        $findings = array_merge($findings, self::checkWorldWritableFiles());
        $findings = array_merge($findings, self::checkHighCpuUsers());
        $findings = array_merge($findings, self::checkHighMemoryUsers());
        $findings = array_merge($findings, self::checkSuspiciousPhpFiles());
        $findings = array_merge($findings, self::checkSymlinkViolations());
        $findings = array_merge($findings, self::checkExcessiveProcesses());
        $findings = array_merge($findings, self::checkDiskUsage());
        $findings = array_merge($findings, self::checkMailQueue());
        $findings = array_merge($findings, self::checkLoginFailures());
        $findings = array_merge($findings, self::checkFtpChrootIsolation());
        $findings = array_merge($findings, self::checkBackupUsage());

        return [
            'timestamp' => now()->toISOString(),
            'total_findings' => count($findings),
            'findings' => $findings,
        ];
    }

    /**
     * Find world-writable files in user home directories.
     */
    protected static function checkWorldWritableFiles(): array
    {
        $output = Process::timeout(30)->run(
            "find /home -type f -perm -0002 -not -path '*/tmp/*' 2>/dev/null | head -20"
        )->output();

        $files = array_filter(explode("\n", trim($output)));
        if (empty($files)) return [];

        return [[
            'type' => 'world_writable_files',
            'severity' => 'high',
            'count' => count($files),
            'details' => array_slice($files, 0, 10),
            'message' => 'World-writable files found in user directories',
        ]];
    }

    /**
     * Find users consuming excessive CPU.
     */
    protected static function checkHighCpuUsers(): array
    {
        $output = Process::timeout(10)->run(
            "ps aux --no-headers --sort=-%cpu 2>/dev/null | awk '{print $1, $3}' | sort -k2 -rn | head -10"
        )->output();

        $lines = array_filter(explode("\n", trim($output)));
        $highCpu = [];

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[0] !== 'root' && (float)$parts[1] > 50) {
                $highCpu[] = ['user' => $parts[0], 'cpu_percent' => (float)$parts[1]];
            }
        }

        if (empty($highCpu)) return [];

        return [[
            'type' => 'high_cpu_usage',
            'severity' => 'warning',
            'count' => count($highCpu),
            'details' => $highCpu,
            'message' => 'Users with high CPU usage (>50%)',
        ]];
    }

    /**
     * Find users consuming excessive memory.
     */
    protected static function checkHighMemoryUsers(): array
    {
        $output = Process::timeout(10)->run(
            "ps aux --no-headers --sort=-%mem 2>/dev/null | awk '{sum[$1]+=$6} END {for (u in sum) print u, sum[u]}' | sort -k2 -rn | head -10"
        )->output();

        $lines = array_filter(explode("\n", trim($output)));
        $highMem = [];
        $threshold = 1024 * 1024; // 1GB in KB

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[0] !== 'root' && (int)$parts[1] > $threshold) {
                $highMem[] = [
                    'user' => $parts[0],
                    'memory_mb' => round((int)$parts[1] / 1024),
                ];
            }
        }

        if (empty($highMem)) return [];

        return [[
            'type' => 'high_memory_usage',
            'severity' => 'warning',
            'count' => count($highMem),
            'details' => $highMem,
            'message' => 'Users with high memory usage (>1GB)',
        ]];
    }

    /**
     * Find suspicious PHP files (potential malware/shells).
     */
    protected static function checkSuspiciousPhpFiles(): array
    {
        // Use single quotes for grep pattern to avoid PHP variable interpolation
        $pattern = '(eval\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)|base64_decode\s*\(\s*\$_|system\s*\(\s*\$_|passthru|shell_exec\s*\(\s*\$_)';
        $cmd = "find /home -name '*.php' -newer /etc/hostname -exec grep -l -E " . escapeshellarg($pattern) . " {} + 2>/dev/null | head -20";
        $output = Process::timeout(30)->run($cmd)->output();

        $files = array_filter(explode("\n", trim($output)));
        if (empty($files)) return [];

        return [[
            'type' => 'suspicious_php_files',
            'severity' => 'critical',
            'count' => count($files),
            'details' => array_slice($files, 0, 10),
            'message' => 'Suspicious PHP files detected (potential malware)',
        ]];
    }

    /**
     * Find symlinks pointing outside user home directories.
     */
    protected static function checkSymlinkViolations(): array
    {
        $output = Process::timeout(30)->run(
            "find /home -maxdepth 5 -type l 2>/dev/null | while read link; do
                target=\$(readlink -f \"\$link\" 2>/dev/null)
                home=\$(echo \"\$link\" | sed 's|/home/\\([^/]\\+\\)/.*|/home/\\1|')
                if [ -n \"\$target\" ] && [ -n \"\$home\" ] && ! echo \"\$target\" | grep -q \"^\$home\"; then
                    echo \"\$link -> \$target\"
                fi
            done | head -20"
        )->output();

        $violations = array_filter(explode("\n", trim($output)));
        if (empty($violations)) return [];

        return [[
            'type' => 'symlink_violations',
            'severity' => 'critical',
            'count' => count($violations),
            'details' => array_slice($violations, 0, 10),
            'message' => 'Symlinks pointing outside user home directory',
        ]];
    }

    /**
     * Find users with too many processes.
     */
    protected static function checkExcessiveProcesses(): array
    {
        $output = Process::timeout(10)->run(
            "ps aux --no-headers 2>/dev/null | awk '{print $1}' | sort | uniq -c | sort -rn | head -10"
        )->output();

        $lines = array_filter(explode("\n", trim($output)));
        $excessive = [];
        $threshold = 50;

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[1] !== 'root' && (int)$parts[0] > $threshold) {
                $excessive[] = ['user' => $parts[1], 'processes' => (int)$parts[0]];
            }
        }

        if (empty($excessive)) return [];

        return [[
            'type' => 'excessive_processes',
            'severity' => 'warning',
            'count' => count($excessive),
            'details' => $excessive,
            'message' => "Users with >{$threshold} processes",
        ]];
    }

    /**
     * Check per-user disk usage against limits.
     */
    protected static function checkDiskUsage(): array
    {
        $output = Process::timeout(30)->run(
            "du -sm /home/*/ 2>/dev/null | sort -rn | head -10"
        )->output();

        $lines = array_filter(explode("\n", trim($output)));
        $highUsage = [];
        $threshold = 5120; // 5GB

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $size = (int)$parts[0];
                $dir = rtrim($parts[1], '/');
                $user = basename($dir);
                if ($user !== 'lost+found' && $size > $threshold) {
                    $highUsage[] = ['user' => $user, 'disk_mb' => $size];
                }
            }
        }

        if (empty($highUsage)) return [];

        return [[
            'type' => 'high_disk_usage',
            'severity' => 'warning',
            'count' => count($highUsage),
            'details' => $highUsage,
            'message' => "Users with >5GB disk usage",
        ]];
    }

    /**
     * Check mail queue size for spam indicators.
     */
    protected static function checkMailQueue(): array
    {
        $output = Process::timeout(10)->run(
            "mailq 2>/dev/null | tail -1"
        )->output();

        // mailq summary line: "-- N requests (Q K bytes)"
        if (preg_match('/(\d+)\s+request/i', $output, $m)) {
            $count = (int) $m[1];
            if ($count > 100) {
                return [[
                    'type' => 'large_mail_queue',
                    'severity' => $count > 500 ? 'critical' : 'warning',
                    'count' => $count,
                    'message' => "Mail queue has {$count} messages (possible spam)",
                ]];
            }
        }

        return [];
    }

    /**
     * Check for repeated failed login attempts.
     */
    protected static function checkLoginFailures(): array
    {
        // Count failed auth attempts in the last hour from secure log
        $output = Process::timeout(10)->run(
            "journalctl -u sshd --since '1 hour ago' 2>/dev/null | grep -c 'Failed password' || " .
            "grep -c 'Failed password' /var/log/secure 2>/dev/null || echo 0"
        )->output();

        $count = (int) trim($output);
        if ($count > 20) {
            return [[
                'type' => 'login_failures',
                'severity' => $count > 100 ? 'critical' : 'warning',
                'count' => $count,
                'message' => "{$count} failed login attempts in the last hour",
            ]];
        }

        return [];
    }

    /**
     * Verify FTP chroot isolation is properly configured.
     */
    protected static function checkFtpChrootIsolation(): array
    {
        $result = FtpService::verifyChrootIsolation();
        if (!$result['chroot_configured']) {
            return [[
                'type' => 'ftp_chroot_issues',
                'severity' => 'high',
                'count' => count($result['issues']),
                'details' => $result['issues'],
                'message' => 'FTP chroot isolation not fully configured',
            ]];
        }

        return [];
    }

    /**
     * Check backup storage usage.
     */
    protected static function checkBackupUsage(): array
    {
        $output = Process::timeout(10)->run(
            "du -sm /backup/ 2>/dev/null | awk '{print $1}'"
        )->output();

        $totalMb = (int) trim($output);
        if ($totalMb > 51200) { // >50GB
            return [[
                'type' => 'high_backup_usage',
                'severity' => 'warning',
                'disk_mb' => $totalMb,
                'message' => "Backup storage using {$totalMb}MB (>50GB)",
            ]];
        }

        return [];
    }
}
