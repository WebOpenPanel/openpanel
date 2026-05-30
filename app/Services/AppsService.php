<?php

namespace App\Services;

class AppsService
{
    const APPS_DIR = '/usr/local/openpanel/include/apps/';

    public static function listAvailable(): array
    {
        $apps = [];
        $softaculous = self::checkSoftaculous();
        $sitepad = self::checkSitepad();
        if ($softaculous) $apps[] = ['name' => 'Softaculous', 'installed' => true, 'type' => 'auto_installer'];
        if ($sitepad) $apps[] = ['name' => 'Sitepad', 'installed' => true, 'type' => 'site_builder'];
        return $apps;
    }

    public static function checkSoftaculous(): bool
    {
        return file_exists('/usr/local/openpanel/.conf/softaculous.conf') || is_dir('/usr/local/openpanel/.softaculous');
    }

    public static function checkSitepad(): bool
    {
        return file_exists('/usr/local/openpanel/.conf/sitepad.conf') || is_dir('/usr/local/sitepad');
    }

    public static function installSoftaculous(): string
    {
        return ShellService::exec('/usr/local/openpanel/include/apps/softaculous_install.php 2>&1');
    }

    public static function installSitepad(): string
    {
        return ShellService::exec('/usr/local/openpanel/include/apps/sitepad_install.php 2>&1');
    }
}
