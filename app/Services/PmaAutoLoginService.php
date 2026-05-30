<?php

namespace App\Services;

class PmaAutoLoginService
{
    public static function isInstalled(): bool
    {
        return is_dir('/usr/local/openpanel/htdocs/pma') || is_dir('/usr/local/openpanel/htdocs/admin/pma');
    }

    public static function getUrl(): string
    {
        return '/pma/';
    }

    public static function getRootPassword(): string
    {
        $content = ShellService::readFile('/root/.my.cnf');
        if (preg_match('/password\s*=\s*(\S+)/', $content, $m)) {
            return trim($m[1]);
        }
        return '123456';
    }
}
