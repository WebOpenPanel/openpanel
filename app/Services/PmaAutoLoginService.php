<?php

namespace App\Services;

class PmaAutoLoginService
{
    public static function isInstalled(): bool
    {
        return PhpMyAdminService::isInstalled();
    }

    public static function getUrl(): string
    {
        return PhpMyAdminService::url();
    }

    public static function getRootPassword(): string
    {
        return '';
    }
}
