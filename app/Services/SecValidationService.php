<?php

namespace App\Services;

class SecValidationService
{
    public static function validateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function validateDomain(string $domain): bool
    {
        return preg_match('/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.[A-Za-z0-9-]{1,63})*\.[A-Za-z]{2,}$/', $domain);
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePort(int $port): bool
    {
        return $port >= 1 && $port <= 65535;
    }

    public static function validateUsername(string $username): bool
    {
        return preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username);
    }

    public static function validatePassword(string $password, int $minLength = 8): array
    {
        $errors = [];
        if (strlen($password) < $minLength) $errors[] = "Password must be at least {$minLength} characters";
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain uppercase letter';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password must contain lowercase letter';
        if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain digit';
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public static function validatePath(string $path): bool
    {
        return !str_contains($path, '..') && !str_contains($path, '//');
    }

    public static function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    }

    public static function checkReservedUsername(string $username): bool
    {
        $reserved = ['root', 'admin', 'openpanel-srv', 'mysql', 'apache', 'nginx', 'postfix', 'dovecot', 'named', 'ftp', 'mail', 'nobody', 'dbus', 'sshd', 'rpc', 'vmail'];
        return in_array(strtolower($username), $reserved);
    }

    public static function isIpBlacklisted(string $ip): bool
    {
        $blacklist = '/usr/local/openpanel/.conf/ip_blacklist';
        if (!file_exists($blacklist)) return false;
        $content = ShellService::readFile($blacklist);
        return in_array($ip, array_filter(explode("\n", $content)));
    }
}
