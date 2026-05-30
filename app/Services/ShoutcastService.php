<?php

namespace App\Services;

class ShoutcastService
{
    const SHOUTCAST_PATH = '/usr/local/shoutcast';
    const SHOUTCAST_LOG = '/var/log/shoutcast.log';

    public static function isInstalled(): bool
    {
        return is_dir(self::SHOUTCAST_PATH);
    }

    public static function install(): string
    {
        return ShellService::exec('/usr/local/openpanel/include/shoutcast_install.php 2>&1');
    }

    public static function uninstall(): string
    {
        self::stop();
        return ShellService::exec('rm -rf ' . self::SHOUTCAST_PATH . ' 2>&1');
    }

    public static function start(): string { return ServerService::serviceAction('start', 'shoutcast'); }
    public static function stop(): string { return ServerService::serviceAction('stop', 'shoutcast'); }
    public static function restart(): string { return ServerService::serviceAction('restart', 'shoutcast'); }

    public static function status(): array
    {
        $running = trim(ShellService::exec("pgrep -f sc_serv >/dev/null 2>&1 && echo yes || echo no"));
        return ['running' => $running === 'yes'];
    }

    public static function getConfig(): string
    {
        return ShellService::readFile(self::SHOUTCAST_PATH . '/sc_serv.conf');
    }

    public static function saveConfig(string $content): bool
    {
        ShellService::writeFile(self::SHOUTCAST_PATH . '/sc_serv.conf', $content);
        return true;
    }
}
