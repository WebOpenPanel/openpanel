<?php

namespace App\Services;

class MigrationService
{
    const MIGRATION_DIR = '/usr/local/openpanel/.conf/migration/';
    const MIGRATION_LOG = '/var/log/openpanel/migration.log';

    public static function serverTransfer(array $data): array
    {
        $remoteHost = $data['remote_host'] ?? '';
        $remotePort = $data['remote_port'] ?? '22';
        $remoteKey = $data['remote_key'] ?? '';
        $remoteUser = $data['remote_user'] ?? 'root';
        $username = $data['username'] ?? '';

        if (empty($remoteHost) || empty($username)) {
            return ['success' => false, 'message' => 'Remote host and username required'];
        }

        $keyFile = '';
        if (!empty($remoteKey)) {
            $keyFile = self::MIGRATION_DIR . 'migration_key_' . time() . '.rsa';
            if (!is_dir(self::MIGRATION_DIR)) @mkdir(self::MIGRATION_DIR, 0700, true);
            file_put_contents($keyFile, base64_decode($remoteKey));
            chmod($keyFile, 0600);
        }

        $keyOpt = $keyFile ? '-i ' . escapeshellarg($keyFile) : '';
        $cmd = "rsync -avz -e 'ssh -p {$remotePort} {$keyOpt} -o StrictHostKeyChecking=no' " .
               escapeshellarg($remoteUser . '@' . $remoteHost . ':/home/' . $username) . ' ' .
               escapeshellarg('/home/' . $username) . ' 2>&1';

        ShellService::execBackground($cmd . ' >> ' . self::MIGRATION_LOG . ' 2>&1');

        if ($keyFile && file_exists($keyFile)) @unlink($keyFile);

        return ['success' => true, 'message' => 'Transfer started. Check migration log.'];
    }

    public static function cpanelTransfer(array $data): array
    {
        $remoteHost = $data['remote_host'] ?? '';
        $remotePort = $data['remote_port'] ?? '22';
        $remoteKey = $data['remote_key'] ?? '';
        $remoteUser = $data['remote_user'] ?? 'root';
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($remoteHost) || empty($username)) {
            return ['success' => false, 'message' => 'Remote host and username required'];
        }

        if (!is_dir(self::MIGRATION_DIR)) @mkdir(self::MIGRATION_DIR, 0700, true);

        $keyFile = '';
        if (!empty($remoteKey)) {
            $keyFile = self::MIGRATION_DIR . 'cpanel_key_' . time() . '.rsa';
            file_put_contents($keyFile, base64_decode($remoteKey));
            chmod($keyFile, 0600);
        }

        $backupFile = self::MIGRATION_DIR . $username . '_cpanel_backup.tar.gz';
        $keyOpt = $keyFile ? '-i ' . escapeshellarg($keyFile) : '';

        $scpCmd = "scp -P {$remotePort} {$keyOpt} -o StrictHostKeyChecking=no " .
                  escapeshellarg($remoteUser . '@' . $remoteHost . ':/home/' . $username . '/backup-*.tar.gz') . ' ' .
                  escapeshellarg($backupFile) . ' 2>&1';

        ShellService::execBackground($scpCmd . ' >> ' . self::MIGRATION_LOG . ' 2>&1 && ' .
            '/usr/local/openpanel/include/migration_cpanel.php ' .
            escapeshellarg($backupFile) . ' ' . escapeshellarg($username) . ' >> ' . self::MIGRATION_LOG . ' 2>&1');

        if ($keyFile && file_exists($keyFile)) @unlink($keyFile);

        return ['success' => true, 'message' => 'cPanel migration started. Check migration log.'];
    }

    public static function getMigrationLog(int $lines = 50): string
    {
        return ShellService::exec('tail -n ' . $lines . ' ' . self::MIGRATION_LOG . ' 2>/dev/null');
    }

    public static function listBackups(): array
    {
        if (!is_dir(self::MIGRATION_DIR)) return [];
        $backups = [];
        foreach (ShellService::dirList(self::MIGRATION_DIR) as $file) {
            if (preg_match('/\.tar\.gz$/', $file)) {
                $path = self::MIGRATION_DIR . $file;
                $backups[] = ['name' => $file, 'path' => $path, 'size' => filesize($path), 'modified' => date('Y-m-d H:i:s', filemtime($path))];
            }
        }
        return $backups;
    }

    public static function restoreFromBackup(string $backupFile, string $username): string
    {
        return ShellService::exec('/usr/local/openpanel/include/migration_cpanel.php ' .
            escapeshellarg($backupFile) . ' ' . escapeshellarg($username) . ' 2>&1');
    }
}
