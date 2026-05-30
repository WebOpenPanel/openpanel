<?php

namespace App\Services;

class ApiService
{
    const APIKEY_FILE = '/root/.conf/apikey';

    public static function getApiKey(): string
    {
        if (!file_exists(self::APIKEY_FILE)) return '';
        return trim(ShellService::readFile(self::APIKEY_FILE));
    }

    public static function generateApiKey(): string
    {
        $key = bin2hex(random_bytes(32));
        ShellService::writeFile(self::APIKEY_FILE, $key);
        ShellService::exec('chmod 0600 ' . self::APIKEY_FILE);
        return $key;
    }

    public static function deleteApiKey(): bool
    {
        if (file_exists(self::APIKEY_FILE)) @unlink(self::APIKEY_FILE);
        return true;
    }

    public static function isActive(): bool
    {
        return file_exists(self::APIKEY_FILE) && !empty(trim(ShellService::readFile(self::APIKEY_FILE)));
    }

    public static function validateKey(string $key): bool
    {
        return hash_equals(self::getApiKey(), $key);
    }
}
