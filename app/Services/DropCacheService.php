<?php

namespace App\Services;

class DropCacheService
{
    public static function drop(): string
    {
        return ShellService::exec('echo 1 > /proc/sys/vm/drop_caches 2>&1');
    }

    public static function dropAll(): string
    {
        ShellService::exec('sync 2>&1');
        return ShellService::exec('echo 3 > /proc/sys/vm/drop_caches 2>&1');
    }
}
