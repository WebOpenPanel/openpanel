<?php

namespace App\Services;

class SpamExpertsService
{
    const SPAMEXPERTS_PATH = '/usr/local/openpanel/.conf/spamexperts/';
    const SPAMEXPERTS_LOG = '/var/log/openpanel/spamexperts.log';

    public static function isInstalled(): bool
    {
        return is_dir(self::SPAMEXPERTS_PATH) && file_exists(self::SPAMEXPERTS_PATH . 'config.json');
    }

    public static function install(): bool
    {
        if (!is_dir(self::SPAMEXPERTS_PATH)) @mkdir(self::SPAMEXPERTS_PATH, 0755, true);
        return true;
    }

    public static function getConfig(): array
    {
        $configFile = self::SPAMEXPERTS_PATH . 'config.json';
        if (!file_exists($configFile)) return [];
        return json_decode(file_get_contents($configFile), true) ?? [];
    }

    public static function saveConfig(array $config): bool
    {
        if (!is_dir(self::SPAMEXPERTS_PATH)) @mkdir(self::SPAMEXPERTS_PATH, 0755, true);
        return file_put_contents(self::SPAMEXPERTS_PATH . 'config.json', json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }

    public static function addDomain(string $domain, string $server): bool
    {
        $config = self::getConfig();
        $config['domains'] = $config['domains'] ?? [];
        $config['domains'][$domain] = ['server' => $server, 'added_at' => date('Y-m-d H:i:s')];
        return self::saveConfig($config);
    }

    public static function removeDomain(string $domain): bool
    {
        $config = self::getConfig();
        unset($config['domains'][$domain]);
        return self::saveConfig($config);
    }

    public static function listDomains(): array
    {
        $config = self::getConfig();
        return $config['domains'] ?? [];
    }
}
