<?php

namespace App\Services;

class AutoUpdateService
{
    const UPDATE_CHECK_URL = 'http://auto-updates.control-webpanel.com/auto_versions.ini';
    const CONFIG_FILE = '/usr/local/openpanel/.conf/updatecheck_config.json';

    public static function checkForUpdates(): array
    {
        $remoteData = @file_get_contents(self::UPDATE_CHECK_URL);
        if (!$remoteData) return ['status' => 'error', 'message' => 'Failed to check updates'];

        $remoteVersions = [];
        foreach (array_filter(explode("\n", $remoteData)) as $line) {
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $remoteVersions[trim($key)] = trim($val);
            }
        }

        $localPma = self::getLocalVersion('/usr/local/openpanel/var/services/pma/README', '/Version (.*)/');
        $localRc = self::getLocalVersion('/usr/local/openpanel/var/services/roundcube/index.php', '/Version (.*)/', '|');

        $updates = [];
        if (!empty($localPma) && !empty($remoteVersions['PMAVERSION'] ?? '') && version_compare($localPma, $remoteVersions['PMAVERSION'], '<')) {
            $updates[] = ['name' => 'phpMyAdmin', 'current' => $localPma, 'available' => $remoteVersions['PMAVERSION']];
        }
        if (!empty($localRc) && !empty($remoteVersions['RCVERSION'] ?? '') && version_compare($localRc, $remoteVersions['RCVERSION'], '<')) {
            $updates[] = ['name' => 'Roundcube', 'current' => $localRc, 'available' => $remoteVersions['RCVERSION']];
        }

        return ['status' => 'success', 'updates' => $updates, 'remote_versions' => $remoteVersions];
    }

    public static function getConfig(): array
    {
        if (!file_exists(self::CONFIG_FILE)) return self::createDefaultConfig();
        $data = json_decode(file_get_contents(self::CONFIG_FILE), true);
        return $data ?: self::createDefaultConfig();
    }

    public static function saveConfig(array $config): bool
    {
        return file_put_contents(self::CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }

    public static function updatePma(): string
    {
        return ShellService::exec('sh /scripts/mysql_phpmyadmin_update 2>&1');
    }

    public static function updateRoundcube(): string
    {
        return ShellService::exec('sh /scripts/mail_roundcube_update 2>&1');
    }

    public static function shouldCheck(): bool
    {
        $config = self::getConfig();
        $lastCheck = $config['lastcheck'] ?? '';
        if (empty($lastCheck)) return true;
        $days = match ($config['frecuency'] ?? 'weekly') {
            'daily' => 1,
            'monthly' => 30,
            default => 7,
        };
        return strtotime($lastCheck) < strtotime("-{$days} days");
    }

    public static function autoCheck(): void
    {
        if (!self::shouldCheck()) return;
        $updates = self::checkForUpdates();
        $config = self::getConfig();
        $config['lastcheck'] = date('Y-m-d');

        foreach ($config['soft'] ?? [] as &$software) {
            $name = $software['Name'] ?? '';
            foreach ($updates['updates'] ?? [] as $update) {
                if ($update['name'] === $name) {
                    if (($software['Check'] ?? 'manual') === 'automatic') {
                        if ($name === 'phpMyAdmin') self::updatePma();
                        if ($name === 'Roundcube') self::updateRoundcube();
                    } else {
                        NotificationService::addNotification('update', "Update available: {$name} {$update['available']}", 'info');
                    }
                    $software['LastCheck'] = date('Y-m-d');
                }
            }
        }

        self::saveConfig($config);
    }

    private static function getLocalVersion(string $file, string $pattern, string $strip = ''): string
    {
        if (!file_exists($file)) return '';
        $content = file_get_contents($file);
        if (!preg_match($pattern, $content, $m)) return '';
        return trim(str_replace($strip, '', $m[1]));
    }

    private static function createDefaultConfig(): array
    {
        $config = [
            'frecuency' => 'weekly',
            'lastcheck' => date('Y-m-d'),
            'soft' => [
                ['Name' => 'phpMyAdmin', 'Code' => 'PMA', 'Check' => 'manual', 'LastCheck' => date('Y-m-d')],
                ['Name' => 'Roundcube', 'Code' => 'RC', 'Check' => 'manual', 'LastCheck' => date('Y-m-d')],
            ],
        ];
        self::saveConfig($config);
        return $config;
    }
}
