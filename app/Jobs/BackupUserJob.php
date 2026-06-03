<?php

namespace App\Jobs;

use App\Services\ShellService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BackupUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public string $username,
        public bool $includeDatabases = true,
        public bool $includeEmail = true,
        public bool $includeCron = true,
    ) {}

    public function handle(): void
    {
        $home = "/home/{$this->username}";
        $backupDir = config('openpanel.paths.backup_dir', '/backup');

        if (!is_dir($home)) {
            Log::error("BackupUserJob: User home not found: {$home}");
            return;
        }

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $archive = "{$backupDir}/{$this->username}_{$timestamp}.tar.gz";
        $tmpDir = "/tmp/openpanel_backup_{$this->username}_{$timestamp}";

        try {
            mkdir($tmpDir, 0755, true);

            $excludes = [
                '--exclude=.bash_history',
                '--exclude=.cache',
                '--exclude=.local/share/Trash',
                '--exclude=*.sock',
            ];
            $excludesStr = implode(' ', array_map('escapeshellarg', $excludes));
            $archiveEsc = escapeshellarg($archive);
            $userEsc = escapeshellarg($this->username);

            ShellService::exec("tar -czf {$archiveEsc} {$excludesStr} -C /home {$userEsc} 2>&1", 1200);

            if ($this->includeDatabases) {
                $this->backupDatabases($tmpDir, $archive);
            }

            if ($this->includeCron) {
                $cronFile = "/var/spool/cron/{$this->username}";
                if (file_exists($cronFile)) {
                    copy($cronFile, "{$tmpDir}/cron_backup");
                    ShellService::exec("tar -rf " . escapeshellarg(str_replace('.gz', '', $archive)) . " -C {$tmpDir} cron_backup 2>&1");
                }
            }

            if (file_exists($archive)) {
                $sizeMB = number_format(filesize($archive) / 1024 / 1024, 2);
                Log::info("BackupUserJob: Completed {$archive} ({$sizeMB} MB)");
                ShellService::exec("echo '[backup] {$this->username} {$archive} {$sizeMB}MB' >> " . escapeshellarg(config('openpanel.security.log_file', '/var/log/openpanel/audit.log')));
            } else {
                Log::error("BackupUserJob: Archive not created for {$this->username}");
            }
        } catch (\Throwable $e) {
            Log::error("BackupUserJob: Failed for {$this->username}: {$e->getMessage()}");
            if (file_exists($archive)) {
                @unlink($archive);
            }
        } finally {
            ShellService::exec("rm -rf " . escapeshellarg($tmpDir));
        }
    }

    protected function backupDatabases(string $tmpDir, string $archive): void
    {
        $user = escapeshellarg($this->username);
        $dbNamePattern = "{$this->username}_%";

        $dbs = ShellService::exec("mysql -N -e \"SHOW DATABASES LIKE '{$dbNamePattern}'\" 2>/dev/null");

        if (empty($dbs)) {
            return;
        }

        $dbList = array_filter(explode("\n", $dbs));

        foreach ($dbList as $db) {
            $db = trim($db);
            if (empty($db)) continue;

            $dbEsc = escapeshellarg($db);
            $sqlFile = "{$tmpDir}/{$db}.sql";
            $sqlFileEsc = escapeshellarg($sqlFile);

            ShellService::exec("mysqldump --single-transaction --routines --triggers {$dbEsc} > {$sqlFileEsc} 2>/dev/null", 600);
        }

        $archiveBase = str_replace('.gz', '', $archive);
        $archiveBaseEsc = escapeshellarg($archiveBase);
        $tmpDirEsc = escapeshellarg($tmpDir);

        ShellService::exec("tar -rf {$archiveBaseEsc} -C {$tmpDirEsc} . 2>&1");
        ShellService::exec("gzip -f {$archiveBaseEsc} 2>&1");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("BackupUserJob permanently failed for {$this->username}: {$exception->getMessage()}");
    }
}
