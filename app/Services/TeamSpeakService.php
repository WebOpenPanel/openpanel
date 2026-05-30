<?php

namespace App\Services;

class TeamSpeakService
{
    const TS3_PATH = '/usr/local/teamspeak3';

    public static function isInstalled(): bool
    {
        return is_dir(self::TS3_PATH);
    }

    public static function install(): string
    {
        return ShellService::exec('/usr/local/openpanel/include/ts3_install.sh 2>&1');
    }

    public static function uninstall(): string
    {
        self::stop();
        return ShellService::exec('rm -rf ' . self::TS3_PATH . ' 2>&1');
    }

    public static function start(): string { return ServerService::serviceAction('start', 'teamspeak'); }
    public static function stop(): string { return ServerService::serviceAction('stop', 'teamspeak'); }
    public static function restart(): string { return ServerService::serviceAction('restart', 'teamspeak'); }

    public static function status(): array
    {
        $running = trim(ShellService::exec("pgrep -f ts3server >/dev/null 2>&1 && echo yes || echo no"));
        return ['running' => $running === 'yes'];
    }
}
