<?php

namespace App\Services;

class SshScreenService
{
    public static function isInstalled(): bool
    {
        return !empty(trim(ShellService::exec('which screen 2>/dev/null')));
    }

    public static function install(): string
    {
        return ShellService::exec('yum -y install screen 2>&1');
    }

    public static function listSessions(): string
    {
        return ShellService::exec('screen -ls 2>&1');
    }

    public static function killSession(string $session): string
    {
        return ShellService::exec('screen -X -S ' . escapeshellarg($session) . ' quit 2>&1');
    }

    public static function killAll(): string
    {
        return ShellService::exec('pkill screen 2>&1');
    }
}
