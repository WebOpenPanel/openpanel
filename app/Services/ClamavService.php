<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ClamavService
{
    protected static string $scanLogDir = '/var/log/openpanel/clamav';
    protected static string $quarantineDir = '/var/quarantine/openpanel';
    protected static string $userConfigDir = '/usr/local/openpanel/.conf/clamav';

    public static function isInstalled(): bool
    {
        return (bool)ShellService::exec('which clamscan 2>/dev/null');
    }

    public static function install(): array
    {
        $output = ShellService::exec('dnf -y install clamav clamav-update clamd 2>&1');
        if (self::isInstalled()) {
            ShellService::exec('systemctl enable clamd@scan && systemctl start clamd@scan');
            self::updateDefinitions();
            return ['success' => true, 'message' => 'ClamAV installed.', 'output' => $output];
        }
        return ['success' => false, 'message' => 'Installation failed.', 'output' => $output];
    }

    public static function getVersion(): string
    {
        return trim(ShellService::exec('clamscan --version 2>/dev/null'));
    }

    public static function updateDefinitions(): array
    {
        $output = ShellService::exec('freshclam 2>&1');
        return ['success' => stripos($output, 'error') === false, 'output' => $output];
    }

    public static function scanUser(string $user, array $options = []): array
    {
        $homeDir = '/home/' . $user;
        if (!is_dir($homeDir)) {
            return ['success' => false, 'message' => "User {$user} not found."];
        }

        $logFile = self::$scanLogDir . "/scan_{$user}_" . date('Ymd_His') . '.log';
        File::ensureDirectoryExists(self::$scanLogDir);
        File::ensureDirectoryExists(self::$quarantineDir);

        $maxSize = $options['max_size'] ?? '50M';
        $recursive = ($options['recursive'] ?? true) ? '-r' : '';
        $infected = ($options['remove'] ?? false) ? '--remove=yes' : '--move="{$quarantineDir}"';

        $cmd = "clamscan {$recursive} --max-filesize={$maxSize} --max-scansize={$maxSize} --log={$logFile} {$homeDir} 2>&1";
        $output = ShellService::exec($cmd, 300);

        $infectedCount = 0;
        if (preg_match('/Infected files:\s*(\d+)/i', $output, $m)) {
            $infectedCount = (int)$m[1];
        }

        return [
            'success' => true,
            'user' => $user,
            'infected' => $infectedCount,
            'log_file' => $logFile,
            'output' => $output,
        ];
    }

    public static function scanPath(string $path, array $options = []): array
    {
        if (!is_dir($path) && !is_file($path)) {
            return ['success' => false, 'message' => "Path {$path} does not exist."];
        }

        $logFile = self::$scanLogDir . '/scan_' . date('Ymd_His') . '.log';
        File::ensureDirectoryExists(self::$scanLogDir);

        $maxSize = $options['max_size'] ?? '50M';
        $output = ShellService::exec("clamscan -r --max-filesize={$maxSize} --log={$logFile} {$path} 2>&1", 600);

        $infectedCount = 0;
        if (preg_match('/Infected files:\s*(\d+)/i', $output, $m)) {
            $infectedCount = (int)$m[1];
        }

        return [
            'success' => true,
            'path' => $path,
            'infected' => $infectedCount,
            'log_file' => $logFile,
            'output' => $output,
        ];
    }

    public static function scanAllUsers(): array
    {
        $users = DB::table('user')->pluck('username')->toArray();
        $results = [];
        foreach ($users as $username) {
            $results[] = self::scanUser($username);
        }
        return $results;
    }

    public static function getQuarantine(): array
    {
        if (!is_dir(self::$quarantineDir)) {
            return [];
        }
        $files = [];
        foreach (File::allFiles(self::$quarantineDir) as $file) {
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }
        return $files;
    }

    public static function restoreFromQuarantine(string $path, string $restoreTo): array
    {
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'File not found in quarantine.'];
        }
        File::ensureDirectoryExists(dirname($restoreTo));
        rename($path, $restoreTo);
        return ['success' => true, 'message' => "File restored to {$restoreTo}."];
    }

    public static function deleteFromQuarantine(string $path): array
    {
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'File not found.'];
        }
        unlink($path);
        return ['success' => true, 'message' => 'File deleted from quarantine.'];
    }

    public static function getScanLogs(int $limit = 20): array
    {
        if (!is_dir(self::$scanLogDir)) {
            return [];
        }
        $logs = [];
        $files = collect(File::files(self::$scanLogDir))->sortByDesc('getMTime')->take($limit);
        foreach ($files as $file) {
            $logs[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }
        return $logs;
    }

    public static function readScanLog(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }
        return file_get_contents($path);
    }
}
