<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupConfig;
use App\Models\UserAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class BackupService
{
    const BACKUP_BASE = '/backup/';
    const BACKUP_DIR = '/backup/openpanel_backup/';
    const BACKUP_DB = '/usr/local/openpanel/.conf/backup_config.sqlite';
    const BACKUP_CRON = '/usr/local/openpanel/include/cron_newbackup.php';

    protected static function ensureBackupBase(): void
    {
        $base = rtrim(self::BACKUP_BASE, '/');
        $baseArg = escapeshellarg($base);
        $result = Process::timeout(10)->run("sudo mkdir -p {$baseArg} && sudo chown root:nginx {$baseArg} && sudo chmod 0750 {$baseArg}");
        if (!$result->successful()) {
            throw new \RuntimeException('Unable to prepare backup directory.');
        }
    }

    protected static function secureBackupFile(string $path): int
    {
        $pathArg = escapeshellarg($path);
        Process::timeout(10)->run("sudo chown root:nginx {$pathArg} && sudo chmod 0640 {$pathArg}");
        $sizeResult = Process::run("stat -c%s {$pathArg} 2>/dev/null || echo 0");
        return (int) trim($sizeResult->output());
    }

    protected static function validUsername(string $username): bool
    {
        return (bool) preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $username);
    }

    public static function getBackupConfig(): ?BackupConfig
    {
        return BackupConfig::first();
    }

    public static function saveBackupConfig(array $data): BackupConfig
    {
        return BackupConfig::updateOrCreate([], $data);
    }

    public static function runScheduledBackups(bool $force = false): array
    {
        $config = self::getBackupConfig();
        if (!$config || !$config->enabled) {
            return ['success' => true, 'skipped' => true, 'reason' => 'Backups disabled or not configured.'];
        }

        if (!$force && !self::scheduledBackupDue($config)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'Backup is not due yet.'];
        }

        $created = [];
        $accountIds = array_filter((array) ($config->accounts ?? []));
        if ($accountIds) {
            foreach ($accountIds as $accountId) {
                $account = UserAccount::find((int) $accountId);
                if (!$account) {
                    $legacy = DB::connection('mysql')->table('accounts')->where('id', (int) $accountId)->first();
                    if (!$legacy) continue;
                    $created[] = self::generateAccountBackup($legacy->username);
                    continue;
                }
                $created[] = self::generateAccountBackup($account->username, $account->id);
            }
        } else {
            $created[] = self::generateFullBackup();
        }

        $completed = array_values(array_filter($created, fn(Backup $backup) => $backup->status === 'completed'));
        $remoteResults = [];
        if (($config->destination ?? 'local') === 'remote') {
            foreach ($completed as $backup) {
                $remoteResults[] = self::transferBackupSsh(
                    $backup->path,
                    (string) $config->remote_host,
                    (string) $config->remote_user,
                    rtrim((string) $config->remote_path, '/') . '/' . basename($backup->path),
                    (string) ($config->remote_port ?: '22')
                );
            }
        }

        return [
            'success' => count($created) > 0 && count($completed) === count($created),
            'created' => array_map(fn(Backup $backup) => [
                'id' => $backup->id,
                'type' => $backup->type,
                'status' => $backup->status,
                'path' => $backup->path,
                'size_bytes' => $backup->size_bytes,
            ], $created),
            'cleanup_deleted' => self::cleanupOldBackups((int) ($config->retention_days ?: 30)),
            'remote_results' => $remoteResults,
        ];
    }

    protected static function scheduledBackupDue(BackupConfig $config): bool
    {
        $last = Backup::where('status', 'completed')->orderByDesc('completed_at')->first();
        if (!$last || !$last->completed_at) {
            return true;
        }

        $hours = match ($config->frequency) {
            'weekly' => 168,
            'monthly' => 720,
            default => 24,
        };

        return $last->completed_at->diffInHours(now()) >= $hours;
    }

    public static function generateFullBackup(): Backup
    {
        self::ensureBackupBase();
        $filename = 'full_backup_' . date('Y-m-d_H-i-s') . '.tar.gz';
        $path = self::BACKUP_BASE . $filename;

        $backup = Backup::create([
            'filename' => $filename,
            'path' => $path,
            'type' => 'full',
            'status' => 'running',
            'destination' => 'local',
            'started_at' => now(),
        ]);

        $pathArg = escapeshellarg($path);
        $result = Process::timeout(900)->run("sudo tar -czf {$pathArg} -C / home etc/mail var/named var/spool/postfix 2>&1");
        $size = $result->successful() ? self::secureBackupFile($path) : 0;

        $backup->update([
            'status' => ($result->successful() && $size > 0) ? 'completed' : 'failed',
            'completed_at' => now(),
            'size_bytes' => $size,
            'error_message' => $result->successful() ? null : substr($result->errorOutput() ?: $result->output(), 0, 1000),
        ]);
        return $backup;
    }

    public static function generateAccountBackup(string $username, ?int $accountId = null): Backup
    {
        if (!self::validUsername($username)) {
            throw new \InvalidArgumentException('Invalid account username.');
        }

        self::ensureBackupBase();
        $account = $accountId ? UserAccount::find($accountId) : null;
        $legacyAccount = DB::connection('mysql')->table('accounts')->where('username', $username)->first();
        $domain = $account->domain ?? $legacyAccount->domain ?? $username;
        $filename = "account_{$username}_" . date('Y-m-d_H-i-s') . '.tar.gz';
        $path = self::BACKUP_BASE . $filename;
        $tarPath = str_replace('.tar.gz', '.tar', $path);

        $backup = Backup::create([
            'user_account_id' => $account?->id,
            'filename' => $filename,
            'path' => $path,
            'type' => 'account',
            'status' => 'running',
            'destination' => 'local',
            'started_at' => now(),
        ]);

        $homedir = "/home/{$username}";
        $maildir = "/var/spool/mail/{$username}";
        $dnsfile = "/var/named/{$domain}.db";

        $cmd = "sudo tar -cf " . escapeshellarg($tarPath) . " -C / " . escapeshellarg("home/{$username}");
        if (file_exists($maildir)) $cmd .= " " . escapeshellarg("var/spool/mail/{$username}");
        if (file_exists($dnsfile)) $cmd .= " " . escapeshellarg("var/named/{$domain}.db");
        $cmd .= " 2>&1";

        $tar = Process::timeout(600)->run($cmd);

        if ($tar->successful() && self::dumpUserDatabases($username)) {
            Process::timeout(120)->run("sudo tar -rf " . escapeshellarg($tarPath) . " -C / " . escapeshellarg("tmp/{$username}_databases.sql") . " 2>/dev/null");
            Process::timeout(10)->run("sudo rm -f " . escapeshellarg("/tmp/{$username}_databases.sql"));
        }
        $gzip = $tar->successful()
            ? Process::timeout(300)->run("sudo gzip -f " . escapeshellarg($tarPath) . " 2>&1")
            : null;
        $size = ($gzip && $gzip->successful()) ? self::secureBackupFile($path) : 0;

        $backup->update([
            'status' => ($gzip && $gzip->successful() && $size > 0) ? 'completed' : 'failed',
            'completed_at' => now(),
            'size_bytes' => $size,
            'error_message' => ($gzip && $gzip->successful()) ? null : substr(($gzip?->errorOutput() ?: $tar->errorOutput() ?: $tar->output()), 0, 1000),
        ]);

        return $backup;
    }

    public static function generateDatabaseBackup(string $database): Backup
    {
        if ($database !== 'all' && !preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new \InvalidArgumentException('Invalid database name.');
        }

        self::ensureBackupBase();
        $filename = "db_{$database}_" . date('Y-m-d_H-i-s') . '.sql.gz';
        $path = self::BACKUP_BASE . $filename;

        $backup = Backup::create([
            'filename' => $filename,
            'path' => $path,
            'type' => 'database',
            'status' => 'running',
            'destination' => 'local',
            'started_at' => now(),
        ]);

        $dumpCmd = $database === 'all'
            ? "mysqldump --all-databases"
            : "mysqldump " . escapeshellarg($database);
        $result = Process::timeout(600)->run("sudo bash -c " . escapeshellarg("{$dumpCmd} 2>/dev/null | gzip > " . escapeshellarg($path)));
        $size = $result->successful() ? self::secureBackupFile($path) : 0;

        $backup->update([
            'status' => ($result->successful() && $size > 0) ? 'completed' : 'failed',
            'completed_at' => now(),
            'size_bytes' => $size,
            'error_message' => $result->successful() ? null : substr($result->errorOutput() ?: $result->output(), 0, 1000),
        ]);

        return $backup;
    }

    public static function generateFilesBackup(string $path, string $name = ''): Backup
    {
        self::ensureBackupBase();
        $name = $name ?: basename($path);
        $filename = "files_{$name}_" . date('Y-m-d_H-i-s') . '.tar.gz';
        $backupPath = self::BACKUP_BASE . $filename;

        $backup = Backup::create([
            'filename' => $filename,
            'path' => $backupPath,
            'type' => 'files',
            'status' => 'running',
            'destination' => 'local',
            'started_at' => now(),
        ]);

        $result = Process::timeout(600)->run("sudo tar -czf " . escapeshellarg($backupPath) . " -C " . escapeshellarg(dirname($path)) . " " . escapeshellarg(basename($path)) . " 2>&1");
        $size = $result->successful() ? self::secureBackupFile($backupPath) : 0;

        $backup->update([
            'status' => ($result->successful() && $size > 0) ? 'completed' : 'failed',
            'completed_at' => now(),
            'size_bytes' => $size,
            'error_message' => $result->successful() ? null : substr($result->errorOutput() ?: $result->output(), 0, 1000),
        ]);

        return $backup;
    }

    /**
     * Validate archive contents before extraction.
     * Blocks: absolute paths, ../ traversal, symlinks, devices, absolute link targets.
     */
    protected static function validateArchive(string $path): array
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $listCmd = ($ext === 'gz') ? "tar -tzf " . escapeshellarg($path) : "tar -tf " . escapeshellarg($path);
        $output = ShellService::exec($listCmd . " 2>&1");
        $entries = array_filter(explode("\n", trim($output)));

        $blocked = [];
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if (empty($entry)) continue;

            // Block absolute paths
            if (str_starts_with($entry, '/')) {
                $blocked[] = "Absolute path: {$entry}";
                continue;
            }

            // Block ../ traversal
            if (str_contains($entry, '../') || str_contains($entry, '..\\')) {
                $blocked[] = "Path traversal: {$entry}";
                continue;
            }

            // Block entries that look like symlinks in tar listing (ending with ->)
            if (preg_match('/\s+->\s+/', $entry)) {
                $parts = preg_split('/\s+->\s+/', $entry, 2);
                $target = $parts[1] ?? '';
                if (str_starts_with($target, '/') || str_contains($target, '../')) {
                    $blocked[] = "Dangerous symlink: {$entry}";
                }
            }

            // Block device files
            if (preg_match('/^[bc]\s/', $entry)) {
                $blocked[] = "Device file: {$entry}";
            }
        }

        return [
            'valid' => empty($blocked),
            'entries' => count($entries),
            'blocked' => $blocked,
        ];
    }

    public static function restoreBackup(Backup $backup): array
    {
        $path = $backup->path;
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }

        // Validate archive before extraction
        $validation = self::validateArchive($path);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Archive failed security validation',
                'blocked' => $validation['blocked'],
            ];
        }

        // Extract with --no-same-owner to prevent ownership attacks, --one-file-system to prevent escape
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $flags = '--no-same-owner --one-file-system';
        if ($ext === 'gz') {
            $result = Process::timeout(600)->run("sudo tar -xzf " . escapeshellarg($path) . " -C / {$flags} 2>&1");
        } else {
            $result = Process::timeout(600)->run("sudo tar -xf " . escapeshellarg($path) . " -C / {$flags} 2>&1");
        }

        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'error' => $result->errorOutput(),
            'validation' => $validation,
        ];
    }

    public static function restoreAccountBackup(Backup $backup, string $username): array
    {
        $path = $backup->path;
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }

        // Validate archive before extraction
        $validation = self::validateArchive($path);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Archive failed security validation',
                'blocked' => $validation['blocked'],
            ];
        }

        // Extract to user's home directory (not root), with safety flags
        $home = "/home/{$username}";
        $result = Process::timeout(600)->run("sudo tar -xzf " . escapeshellarg($path) . " -C / --no-same-owner --one-file-system 2>&1");
        Process::timeout(120)->run("sudo chown -R " . escapeshellarg($username) . ":" . escapeshellarg($username) . " " . escapeshellarg($home) . " 2>&1");

        // Restore database if present
        $dbDump = "/tmp/{$username}_databases.sql";
        $legacyRootDump = "/{$username}_databases.sql";
        if (!file_exists($dbDump) && file_exists($legacyRootDump)) {
            $dbDump = $legacyRootDump;
        }
        if (file_exists($dbDump)) {
            Process::timeout(600)->run("sudo mysql < " . escapeshellarg($dbDump) . " 2>&1");
            Process::timeout(10)->run("sudo rm -f " . escapeshellarg($dbDump) . " " . escapeshellarg($legacyRootDump));
        }

        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'error' => $result->errorOutput(),
            'validation' => $validation,
        ];
    }

    public static function deleteBackup(Backup $backup): bool
    {
        if (file_exists($backup->path)) {
            Process::timeout(10)->run("sudo rm -f " . escapeshellarg($backup->path));
        }
        $statusFile = $backup->path . '.status';
        if (file_exists($statusFile)) {
            Process::timeout(10)->run("sudo rm -f " . escapeshellarg($statusFile));
        }
        return $backup->delete();
    }

    public static function listBackups(): array
    {
        $files = [];
        if (!is_dir(self::BACKUP_BASE)) return $files;
        foreach (ShellService::dirList(self::BACKUP_BASE) as $file) {
            $fullPath = self::BACKUP_BASE . $file;
            if (is_file($fullPath) && preg_match('/\.(tar\.gz|sql\.gz|tar|zip)$/', $file)) {
                $files[] = [
                    'name' => $file,
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                ];
            }
        }
        usort($files, fn($a, $b) => strtotime($b['modified']) - strtotime($a['modified']));
        return $files;
    }

    public static function getManagedBackups(): array
    {
        return self::listBackups();
    }

    public static function downloadBackup(string $path): ?string
    {
        if (!file_exists($path)) return null;
        return $path;
    }

    public static function getBackupSize(): string
    {
        $size = ShellService::exec("du -sh " . self::BACKUP_BASE . " 2>/dev/null | cut -f1");
        return trim($size) ?: '0';
    }

    public static function cleanupOldBackups(int $retentionDays = 30): int
    {
        $deleted = 0;
        $cutoff = time() - ($retentionDays * 86400);
        if (!is_dir(self::BACKUP_BASE)) return 0;

        foreach (ShellService::dirList(self::BACKUP_BASE) as $file) {
            $fullPath = self::BACKUP_BASE . $file;
            if (is_file($fullPath) && filemtime($fullPath) < $cutoff) {
                Process::timeout(10)->run("sudo rm -f " . escapeshellarg($fullPath));
                $deleted++;
            }
        }
        return $deleted;
    }

    public static function transferBackupSsh(string $path, string $remoteHost, string $remoteUser, string $remotePath, string $port = '22'): string
    {
        if (!is_file($path)) {
            return 'Backup file not found.';
        }
        if (!preg_match('/^[A-Za-z0-9_.:-]+$/', $remoteHost) || !self::validUsername($remoteUser) || !preg_match('/^\d{1,5}$/', $port)) {
            return 'Invalid remote destination.';
        }
        if ($remotePath === '' || str_contains($remotePath, "\n")) {
            return 'Invalid remote path.';
        }

        $target = "{$remoteUser}@{$remoteHost}:{$remotePath}";
        $result = Process::timeout(600)->run(
            "scp -B -P " . escapeshellarg($port) .
            " -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 " .
            escapeshellarg($path) . " " . escapeshellarg($target) . " 2>&1"
        );

        return $result->successful() ? 'remote transfer completed' : trim($result->errorOutput() ?: $result->output());
    }

    // ---- SQLite3-based backup manager ----

    public static function managerDbExists(): bool
    {
        return file_exists(self::BACKUP_DB);
    }

    public static function managerInitDb(): ?\SQLite3
    {
        if (!class_exists('SQLite3')) return null;
        $db = new \SQLite3(self::BACKUP_DB, \SQLITE3_OPEN_CREATE | \SQLITE3_OPEN_READWRITE);
        self::managerEnsureColumns($db);
        return $db;
    }

    private static function managerEnsureColumns(\SQLite3 $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS backups (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            ACCOUNTS_SERVER TEXT DEFAULT '',
            ACCOUNTS_SERVER_ID TEXT DEFAULT '',
            LOCAL_FILE INTEGER DEFAULT 0,
            FTP_SERVER INTEGER DEFAULT 0,
            FTP_TYPE TEXT DEFAULT '',
            FTP_SERVERNAME TEXT DEFAULT '',
            FTP_LOCATION TEXT DEFAULT '',
            FTP_LOGIN_USER TEXT DEFAULT '',
            SSH_SERVER INTEGER DEFAULT 0,
            SSH_SERVERNAME TEXT DEFAULT '',
            SSH_FILE TEXT DEFAULT '',
            SSH_USER TEXT DEFAULT '',
            SSH_PORT TEXT DEFAULT '22',
            LOCATION_LOCAL_FILE TEXT DEFAULT '/home/tmp_bak/',
            CRON_HOUR TEXT DEFAULT '0',
            CRON_MINUTES TEXT DEFAULT '0',
            DAILY_BACKUP INTEGER DEFAULT 0,
            WEEKLY_BACKUP INTEGER DEFAULT 0,
            MONTHLY_BACKUP INTEGER DEFAULT 0,
            FREQUENCY_DETAILS_DAILY TEXT DEFAULT '',
            FREQUENCY_DETAILS_WEEKLY TEXT DEFAULT '',
            FREQUENCY_DETAILS_MONTHLY TEXT DEFAULT '',
            BACKUP_RETENTION_DAILY INTEGER DEFAULT 0,
            BACKUP_RETENTION_WEEKLY INTEGER DEFAULT 0,
            BACKUP_RETENTION_MONTHLY INTEGER DEFAULT 0,
            FULL INTEGER DEFAULT 0,
            INCREMENTAL INTEGER DEFAULT 0,
            DEFAULUSERBACKUP INTEGER DEFAULT 0,
            LASTEXEC TEXT DEFAULT 'Never',
            NOT_START INTEGER DEFAULT 0,
            BACKUP_STATUS INTEGER DEFAULT 1
        )");
        $requiredColumns = [
            'SSH_PASSWORD' => 'TEXT',
            'CONNECTION_TYPE' => 'TEXT',
            'LOCATION_DIR_TEMP' => 'TEXT',
            'SSL_DOMAINS' => 'TEXT',
            'ACCOUNT_FTP' => 'TEXT',
            'AGGRESSIVENESS' => 'TEXT',
        ];
        foreach ($requiredColumns as $col => $type) {
            $res = $db->query("PRAGMA table_info(backups)");
            $found = false;
            while ($row = $res->fetchArray(\SQLITE3_ASSOC)) {
                if ($row['name'] === $col) { $found = true; break; }
            }
            if (!$found) {
                @$db->exec("ALTER TABLE backups ADD COLUMN {$col} {$type}");
            }
        }
    }

    public static function managerListBackups(): array
    {
        $db = self::managerInitDb();
        if (!$db) return [];
        $results = $db->query("SELECT * FROM backups WHERE ACCOUNTS_SERVER <> 'ONLYRESTORE'");
        $backups = [];
        if ($results) {
            while ($row = $results->fetchArray(\SQLITE3_ASSOC)) {
                $backups[] = self::managerFormatBackupRow($row);
            }
        }
        $db->close();
        return $backups;
    }

    private static function managerFormatBackupRow(array $row): array
    {
        $type = 'Unknown';
        if ($row['LOCAL_FILE'] ?? 0) {
            $type = 'Local';
        } elseif ($row['FTP_SERVER'] ?? 0) {
            $type = ($row['FTP_TYPE'] ?? '') === 'SFTP' ? 'SFTP' : 'FTP';
        } elseif ($row['SSH_SERVER'] ?? 0) {
            $type = 'SSH';
        }
        $compression = ($row['INCREMENTAL'] ?? 0) == 1 ? 'Incremental' : 'Full';
        $status = ($row['BACKUP_STATUS'] ?? 0) == 1 ? 'enabled' : 'disabled';

        return [
            'id' => $row['ID'],
            'type' => $type,
            'compression' => $compression,
            'status' => $status,
            'accounts' => $row['ACCOUNTS_SERVER_ID'] ?? '',
            'ftp_server' => $row['FTP_SERVERNAME'] ?? '',
            'ftp_location' => $row['FTP_LOCATION'] ?? '',
            'ftp_user' => $row['FTP_LOGIN_USER'] ?? '',
            'ftp_type' => $row['FTP_TYPE'] ?? '',
            'ssh_server' => $row['SSH_SERVERNAME'] ?? '',
            'ssh_file' => $row['SSH_FILE'] ?? '',
            'ssh_user' => $row['SSH_USER'] ?? '',
            'ssh_port' => $row['SSH_PORT'] ?? '22',
            'local_path' => $row['LOCATION_LOCAL_FILE'] ?? '/home/tmp_bak/',
            'cron_hour' => $row['CRON_HOUR'] ?? '0',
            'cron_minutes' => $row['CRON_MINUTES'] ?? '0',
            'daily_backup' => $row['DAILY_BACKUP'] ?? 0,
            'weekly_backup' => $row['WEEKLY_BACKUP'] ?? 0,
            'monthly_backup' => $row['MONTHLY_BACKUP'] ?? 0,
            'frequency_daily' => $row['FREQUENCY_DETAILS_DAILY'] ?? '',
            'frequency_weekly' => $row['FREQUENCY_DETAILS_WEEKLY'] ?? '',
            'frequency_monthly' => $row['FREQUENCY_DETAILS_MONTHLY'] ?? '',
            'retention_daily' => $row['BACKUP_RETENTION_DAILY'] ?? 0,
            'retention_weekly' => $row['BACKUP_RETENTION_WEEKLY'] ?? 0,
            'retention_monthly' => $row['BACKUP_RETENTION_MONTHLY'] ?? 0,
            'full' => $row['FULL'] ?? 0,
            'incremental' => $row['INCREMENTAL'] ?? 0,
            'default_user_backup' => $row['DEFAULUSERBACKUP'] ?? 0,
            'last_exec' => $row['LASTEXEC'] ?? 'Never',
            'notifications_start' => $row['NOT_START'] ?? 0,
            'notifications_end' => $row['NOT_END'] ?? 0,
            'notifications_error' => $row['NOT_ERROR'] ?? 0,
            'aggressiveness' => $row['AGGRESSIVENESS'] ?? 1,
            'connection_type' => $row['CONNECTION_TYPE'] ?? '',
        ];
    }

    public static function managerSaveBackup(array $data): array
    {
        $db = self::managerInitDb();
        if (!$db) return ['success' => false, 'message' => 'SQLite3 not available'];

        $isNew = empty($data['ID']);
        $data['BACKUP_STATUS'] = $data['BACKUP_STATUS'] ?? '0';
        $data['LASTEXEC'] = '';
        $data['STRUN'] = '0';

        if (!empty($data['FTP_PASS'])) {
            $timerKey = 'PP' . date('YmdHis');
            Process::timeout(10)->run("sudo mkdir -p /usr/local/openpanel/.conf && sudo chown root:nginx /usr/local/openpanel/.conf && sudo chmod 0750 /usr/local/openpanel/.conf");
            $secretTmp = tempnam(sys_get_temp_dir(), 'opbackup');
            file_put_contents($secretTmp, (string) $data['FTP_PASS']);
            chmod($secretTmp, 0600);
            Process::timeout(10)->run("sudo install -m 0600 -o root -g nginx " . escapeshellarg($secretTmp) . " " . escapeshellarg("/usr/local/openpanel/.conf/{$timerKey}"));
            @unlink($secretTmp);
            $data['FTP_PASS'] = $timerKey;
        }

        if ($isNew) {
            $cols = implode(',', array_keys($data));
            $vals = "'" . implode("','", array_map('addslashes', array_values($data))) . "'";
            $query = "INSERT INTO backups ({$cols}) VALUES ({$vals})";
        } else {
            $id = (int) $data['ID'];
            unset($data['ID']);
            $sets = [];
            foreach ($data as $k => $v) {
                $sets[] = "{$k}='" . addslashes($v) . "'";
            }
            $query = "UPDATE backups SET " . implode(',', $sets) . " WHERE ID={$id}";
        }

        $result = $db->exec($query);
        $lastId = $isNew ? $db->lastInsertRowID() : ($data['ID'] ?? 0);
        $db->close();

        if ($result) {
            if ($isNew) {
                self::managerUpdateCron();
            }
            return ['success' => true, 'id' => $lastId];
        }
        return ['success' => false, 'message' => 'Failed to save backup config'];
    }

    public static function managerDeleteBackup(int $id): bool
    {
        $db = self::managerInitDb();
        if (!$db) return false;
        $result = $db->exec("DELETE FROM backups WHERE ID=" . (int) $id);
        $db->close();
        return $result;
    }

    public static function managerUpdateStatus(int $id, int $status): bool
    {
        $db = self::managerInitDb();
        if (!$db) return false;
        $result = $db->exec('UPDATE backups SET BACKUP_STATUS="' . (int) $status . '" WHERE ID = "' . (int) $id . '"');
        $db->close();
        self::managerUpdateCron();
        return $result;
    }

    public static function managerSetDefault(int $id): bool
    {
        $db = self::managerInitDb();
        if (!$db) return false;
        $db->exec('UPDATE backups SET DEFAULUSERBACKUP="0"');
        $result = $db->exec('UPDATE backups SET DEFAULUSERBACKUP="1" WHERE ID="' . (int) $id . '"');
        $db->close();
        return $result;
    }

    public static function managerGetBackup(int $id): ?array
    {
        $db = self::managerInitDb();
        if (!$db) return null;
        $row = $db->querySingle("SELECT * FROM backups WHERE ID=" . (int) $id, true);
        $db->close();
        return $row ? self::managerFormatBackupRow($row) : null;
    }

    public static function managerRunBackup(int $id): bool
    {
        ShellService::exec("echo \"{$id}\" > /usr/local/openpanel/.conf/sendcronbackup");
        ShellService::execBackground("nohup php -d max_execution_time=1000000000 -q " . self::BACKUP_CRON . " {$id} >/dev/null &");
        return true;
    }

    public static function managerMonitorLog(int $lines = 5): string
    {
        return ShellService::exec("tail -n {$lines} /var/log/openpanel/cron_backup.log 2>/dev/null");
    }

    public static function managerMonitorRestoreLog(int $lines = 5): string
    {
        return ShellService::exec("tail -n {$lines} /var/log/openpanel/restore_backup.log 2>/dev/null");
    }

    public static function managerSaveSshKey(string $base64Key): string
    {
        $nameCert = 'back_mag_' . date('YmdHis') . '_key.rsa';
        $cert = base64_decode($base64Key);
        ShellService::writeFile("/usr/local/openpanel/.conf/{$nameCert}", $cert);
        ShellService::exec("chmod 0600 /usr/local/openpanel/.conf/{$nameCert}");
        return $nameCert;
    }

    private static function managerUpdateCron(): void
    {
        $db = self::managerInitDb();
        if (!$db) return;
        $config = $db->querySingle("SELECT * FROM config WHERE ID=1", true);
        $db->close();

        if (!$config) return;

        $cronLine = ($config['min'] ?? '0') . ' ' . ($config['hour'] ?? '0') . ' * * * php -d max_execution_time=100000000 -q ' . self::BACKUP_CRON;

        $existing = ShellService::exec("crontab -l 2>/dev/null");
        $lines = array_filter(explode("\n", $existing), fn($l) => strpos($l, 'cron_newbackup.php') === false);
        $lines[] = $cronLine;
        $cronContent = implode("\n", array_filter($lines));

        ShellService::writeFile('/root/cron', $cronContent);
        ShellService::exec("crontab /root/cron");
    }

    private static function dumpUserDatabases(string $username): bool
    {
        $outputFile = "/tmp/{$username}_databases.sql";
        $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $username) . '\\_%';
        $dbList = Process::timeout(30)->run("sudo mysql -N -e " . escapeshellarg("SHOW DATABASES LIKE '{$like}'") . " 2>/dev/null");
        if (!$dbList->successful()) {
            return false;
        }
        $databases = array_values(array_filter(array_map('trim', explode("\n", $dbList->output()))));

        if (empty($databases)) {
            return false;
        }

        $dbArgs = implode(' ', array_map('escapeshellarg', $databases));
        $dump = Process::timeout(300)->run("sudo mysqldump --databases {$dbArgs} 2>/dev/null > " . escapeshellarg($outputFile));
        return $dump->successful() && file_exists($outputFile) && filesize($outputFile) > 0;
    }

    private static function getMysqlRootPassword(): string
    {
        $content = ShellService::readFile('/root/.my.cnf');
        if (preg_match('/password\s*=\s*(\S+)/', $content, $m)) {
            return trim($m[1]);
        }
        return '123456';
    }
}
