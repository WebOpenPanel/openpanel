<?php

namespace App\Services;

class MonitService
{
    const MONIT_CONF = '/etc/monit.conf';
    const MONIT_DIR = '/etc/monit.d/';

    public static function isInstalled(): bool
    {
        return !empty(trim(ShellService::exec('which monit 2>/dev/null')));
    }

    public static function install(): string
    {
        return ShellService::exec('yum -y install monit 2>&1');
    }

    public static function uninstall(): string
    {
        ServerService::serviceAction('stop', 'monit');
        return ShellService::exec('yum -y remove monit 2>&1');
    }

    public static function status(): string
    {
        return trim(ShellService::exec('monit status 2>&1'));
    }

    public static function start(): string { return ServerService::serviceAction('start', 'monit'); }
    public static function stop(): string { return ServerService::serviceAction('stop', 'monit'); }
    public static function restart(): string { return ServerService::serviceAction('restart', 'monit'); }
    public static function reload(): string { return ServerService::serviceAction('reload', 'monit'); }

    public static function summary(): string
    {
        return ShellService::exec('monit summary 2>&1');
    }

    public static function getMainConfig(): string
    {
        return ShellService::readFile(self::MONIT_CONF);
    }

    public static function saveMainConfig(string $content): bool
    {
        ShellService::writeFile(self::MONIT_CONF, $content);
        self::reload();
        return true;
    }

    public static function listServiceConfigs(): array
    {
        if (!is_dir(self::MONIT_DIR)) return [];
        $configs = [];
        foreach (ShellService::dirList(self::MONIT_DIR) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'conf') {
                $configs[] = [
                    'file' => $file,
                    'content' => ShellService::readFile(self::MONIT_DIR . $file),
                ];
            }
        }
        return $configs;
    }

    public static function getServiceConfig(string $file): string
    {
        return ShellService::readFile(self::MONIT_DIR . $file);
    }

    public static function saveServiceConfig(string $file, string $content): bool
    {
        ShellService::writeFile(self::MONIT_DIR . $file, $content);
        self::reload();
        return true;
    }

    public static function deleteServiceConfig(string $file): bool
    {
        if (file_exists(self::MONIT_DIR . $file)) @unlink(self::MONIT_DIR . $file);
        self::reload();
        return true;
    }

    public static function monitorService(string $service): string
    {
        return ShellService::exec('monit monitor ' . escapeshellarg($service) . ' 2>&1');
    }

    public static function unmonitorService(string $service): string
    {
        return ShellService::exec('monit unmonitor ' . escapeshellarg($service) . ' 2>&1');
    }
}
