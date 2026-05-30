<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CloudLinuxService
{
    public static function isInstalled(): bool
    {
        return !empty(trim(ShellService::exec('which cagefsctl 2>/dev/null')));
    }

    public static function isOpenVz(): bool
    {
        return !empty(trim(ShellService::exec('uname -a | grep stab')));
    }

    public static function getCageFsStatus(): string
    {
        return trim(ShellService::exec('/usr/sbin/cagefsctl --cagefs-status 2>/dev/null'));
    }

    public static function getCageFsMode(): string
    {
        return trim(ShellService::exec('/usr/sbin/cagefsctl --display-user-mode 2>/dev/null'));
    }

    public static function enableCageFs(): string { return ShellService::exec('/usr/sbin/cagefsctl --enable-cagefs 2>&1'); }
    public static function disableCageFs(): string { return ShellService::exec('/usr/sbin/cagefsctl --disable-cagefs 2>&1'); }
    public static function updateCageFs(): string { return ShellService::exec('/usr/sbin/cagefsctl --force-update --do-not-ask 2>&1'); }
    public static function enableAll(): string { return ShellService::exec('/usr/sbin/cagefsctl --enable-all 2>&1'); }
    public static function disableAll(): string { return ShellService::exec('/usr/sbin/cagefsctl --disable-all 2>&1'); }
    public static function listEnabled(): string { return ShellService::exec('/usr/sbin/cagefsctl --list-enabled 2>&1'); }
    public static function listDisabled(): string { return ShellService::exec('/usr/sbin/cagefsctl --list-disabled 2>&1'); }

    public static function enableUser(string $username): string { return ShellService::exec('/usr/sbin/cagefsctl --enable ' . escapeshellarg($username) . ' 2>&1'); }
    public static function disableUser(string $username): string { return ShellService::exec('/usr/sbin/cagefsctl --disable ' . escapeshellarg($username) . ' 2>&1'); }

    public static function getLveLimits(): string
    {
        return ShellService::exec('/usr/sbin/lvectl list-user 2>&1');
    }

    public static function setLveUser(string $username, array $limits): string
    {
        $speed = $limits['speed'] ?? '25';
        $io = $limits['io'] ?? '1024';
        $nproc = $limits['nproc'] ?? '100';
        $pmem = $limits['pmem'] ?? '1.0G';
        $vmem = $limits['vmem'] ?? '1.0G';
        $maxEntry = $limits['maxentryp'] ?? '25';
        $cmd = "/usr/sbin/lvectl set-user " . escapeshellarg($username) .
               " --speed={$speed}% --io={$io} --nproc={$nproc} --pmem={$pmem} --vmem={$vmem} --maxEntryProcs={$maxEntry} && lvectl apply all";
        return ShellService::exec($cmd . ' 2>&1');
    }

    public static function getPhpSelectorVersions(): string
    {
        return ShellService::exec('selectorctl --summary --show-native-version 2>&1');
    }

    public static function getUserPhpVersion(string $username): string
    {
        return ShellService::exec('selectorctl --user-current --user=' . escapeshellarg($username) . ' 2>&1');
    }

    public static function setUserPhpVersion(string $username, string $version): string
    {
        return ShellService::exec('selectorctl --set-user-current=' . escapeshellarg($version) . ' --user=' . escapeshellarg($username) . ' 2>&1');
    }

    public static function listPhpVersions(): string
    {
        return ShellService::exec('selectorctl --list 2>&1 | awk \'{print $1}\'');
    }
}
