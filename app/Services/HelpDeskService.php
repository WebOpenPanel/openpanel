<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class HelpDeskService
{
    protected static string $configPath = '/usr/local/openpanel/.conf/helpdesk.json';

    public static function getConfig(): array
    {
        if (!file_exists(self::$configPath)) {
            return self::getDefaultConfig();
        }
        return json_decode(file_get_contents(self::$configPath), true) ?: self::getDefaultConfig();
    }

    public static function saveConfig(array $data): array
    {
        File::ensureDirectoryExists(dirname(self::$configPath));
        file_put_contents(self::$configPath, json_encode($data, JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'Help desk configuration saved.'];
    }

    public static function installPhpMyFAQ(): array
    {
        $docRoot = '/var/www/helpdesk';
        File::ensureDirectoryExists($docRoot);
        $output = ShellService::exec("cd /tmp && curl -sL https://github.com/thorsten/phpMyFAQ/releases/latest/download/phpMyFAQ-*.tar.gz | tar xz -C {$docRoot} --strip-components=1 2>&1");
        ShellService::exec("chown -R nginx:nginx {$docRoot}");
        return ['success' => is_dir($docRoot . '/admin'), 'message' => 'phpMyFAQ downloaded.', 'output' => $output];
    }

    public static function installOSTicket(): array
    {
        $docRoot = '/var/www/helpdesk';
        File::ensureDirectoryExists($docRoot);
        $output = ShellService::exec("cd /tmp && curl -sL https://github.com/osTicket/osTicket/releases/latest/download/osTicket-v*.tar.gz | tar xz -C {$docRoot} --strip-components=1 2>&1");
        ShellService::exec("chown -R nginx:nginx {$docRoot}");
        return ['success' => is_dir($docRoot . '/upload'), 'message' => 'osTicket downloaded.', 'output' => $output];
    }

    public static function installUVDesk(): array
    {
        $docRoot = '/var/www/helpdesk';
        File::ensureDirectoryExists($docRoot);
        $output = ShellService::exec("cd /tmp && curl -sL https://github.com/uvdesk/community-skeleton/releases/latest/download/uvdesk-community-v*.tar.gz | tar xz -C {$docRoot} --strip-components=1 2>&1");
        ShellService::exec("chown -R nginx:nginx {$docRoot}");
        return ['success' => is_dir($docRoot . '/config'), 'message' => 'UVdesk downloaded.', 'output' => $output];
    }

    public static function getAvailableSoftware(): array
    {
        return [
            ['name' => 'phpMyFAQ', 'description' => 'FAQ system with powerful search', 'install' => 'installPhpMyFAQ'],
            ['name' => 'osTicket', 'description' => 'Open source support ticket system', 'install' => 'installOSTicket'],
            ['name' => 'UVdesk', 'description' => 'Open source helpdesk platform', 'install' => 'installUVDesk'],
        ];
    }

    public static function generateNginxConfig(string $domain, string $docRoot = '/var/www/helpdesk'): string
    {
        return "server {
    listen 80;
    server_name {$domain};
    root {$docRoot};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}";
    }

    protected static function getDefaultConfig(): array
    {
        return [
            'enabled' => false,
            'software' => '',
            'domain' => '',
            'doc_root' => '/var/www/helpdesk',
            'admin_email' => '',
            'installed' => false,
        ];
    }
}
