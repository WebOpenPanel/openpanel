<?php

namespace App\Services;

class CgtopService
{
    public static function getTop(): string
    {
        return ShellService::exec('systemd-cgtop -n 1 --no-pager 2>/dev/null');
    }

    public static function getTopCpu(): string
    {
        return ShellService::exec('systemd-cgtop -n 1 --no-pager -o cpu 2>/dev/null');
    }

    public static function getTopMemory(): string
    {
        return ShellService::exec('systemd-cgtop -n 1 --no-pager -o memory 2>/dev/null');
    }

    public static function getCgroupStatus(): string
    {
        return ShellService::exec('systemctl status openpanel_cgroups.service 2>&1');
    }

    public static function restartCgroups(): string
    {
        return ServerService::serviceAction('restart', 'openpanel_cgroups');
    }

    public static function getUserCgroups(): array
    {
        $output = ShellService::exec('systemd-cgls --no-pager 2>/dev/null');
        return ['raw' => $output];
    }
}
