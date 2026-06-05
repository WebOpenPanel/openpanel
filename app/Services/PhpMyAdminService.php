<?php

namespace App\Services;

class PhpMyAdminService
{
    public static function sourcePath(): ?string
    {
        foreach (['/usr/share/phpmyadmin', '/usr/share/phpMyAdmin'] as $path) {
            if (is_file($path . '/index.php')) {
                return $path;
            }
        }

        return null;
    }

    public static function isInstalled(): bool
    {
        return self::sourcePath() !== null;
    }

    public static function url(): string
    {
        return '/phpmyadmin/';
    }

    public static function status(): array
    {
        $config = self::configPath();
        $configContent = $config && is_file($config) ? (file_get_contents($config) ?: '') : '';

        return [
            'installed' => self::isInstalled(),
            'url' => self::url(),
            'source_path' => self::sourcePath(),
            'config_exists' => $config !== null && is_file($config),
            'cookie_auth' => str_contains($configContent, "auth_type'] = 'cookie'") || str_contains($configContent, 'auth_type"] = "cookie"'),
            'root_login_disabled' => is_file('/etc/phpMyAdmin/openpanel-root-block.php')
                || str_contains($configContent, "AllowRoot'] = false")
                || str_contains($configContent, 'deny root from all'),
            'nginx_snippet_exists' => is_file('/etc/nginx/snippets/openpanel-phpmyadmin.conf'),
        ];
    }

    protected static function configPath(): ?string
    {
        foreach (['/etc/phpMyAdmin/config.inc.php', '/etc/phpmyadmin/config.inc.php'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
