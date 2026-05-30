<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class WebServerTemplateService
{
    protected static string $templatesDir = '/usr/local/openpanel/.conf/webserver_templates';
    protected static string $builtinTemplatesDir = '/usr/local/openpanel/resources/templates';

    public static function getTemplates(): array
    {
        $templates = [];

        $builtinDir = self::$builtinTemplatesDir;
        if (is_dir($builtinDir)) {
            foreach (File::files($builtinDir) as $file) {
                if ($file->getExtension() === 'conf') {
                    $templates[] = [
                        'name' => $file->getBasename('.conf'),
                        'type' => 'builtin',
                        'path' => $file->getPathname(),
                        'modified' => $file->getMTime(),
                    ];
                }
            }
        }

        $customDir = self::$templatesDir;
        if (is_dir($customDir)) {
            foreach (File::files($customDir) as $file) {
                if ($file->getExtension() === 'conf') {
                    $templates[] = [
                        'name' => $file->getBasename('.conf'),
                        'type' => 'custom',
                        'path' => $file->getPathname(),
                        'modified' => $file->getMTime(),
                    ];
                }
            }
        }

        return $templates;
    }

    public static function getTemplate(string $name): ?string
    {
        $customPath = self::$templatesDir . '/' . $name . '.conf';
        if (file_exists($customPath)) {
            return file_get_contents($customPath);
        }

        $builtinPath = self::$builtinTemplatesDir . '/' . $name . '.conf';
        if (file_exists($builtinPath)) {
            return file_get_contents($builtinPath);
        }

        return null;
    }

    public static function saveTemplate(string $name, string $content): array
    {
        File::ensureDirectoryExists(self::$templatesDir);
        $path = self::$templatesDir . '/' . $name . '.conf';
        file_put_contents($path, $content);
        return ['success' => true, 'message' => "Template '{$name}' saved."];
    }

    public static function deleteTemplate(string $name): array
    {
        $path = self::$templatesDir . '/' . $name . '.conf';
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'Template not found.'];
        }
        unlink($path);
        return ['success' => true, 'message' => "Template '{$name}' deleted."];
    }

    public static function getAvailableTypes(): array
    {
        return [
            'nginx_php-fpm' => 'Nginx + PHP-FPM',
            'nginx_apache_php-fpm' => 'Nginx + Apache + PHP-FPM',
            'nginx_apache_php-cgi' => 'Nginx + Apache + PHP-CGI',
            'nginx_varnish_apache_php-fpm' => 'Nginx + Varnish + Apache + PHP-FPM',
        ];
    }

    public static function generateVhost(string $type, array $params): string
    {
        $domain = $params['domain'] ?? 'example.com';
        $user = $params['user'] ?? 'user';
        $docRoot = $params['doc_root'] ?? "/home/{$user}/public_html";
        $phpVersion = $params['php_version'] ?? '8.3';
        $ssl = $params['ssl'] ?? false;

        return match ($type) {
            'nginx_php-fpm' => self::generateNginxPhpFpm($domain, $user, $docRoot, $phpVersion, $ssl),
            'nginx_apache_php-fpm' => self::generateNginxApachePhpFpm($domain, $user, $docRoot, $phpVersion, $ssl),
            'nginx_apache_php-cgi' => self::generateNginxApachePhpCgi($domain, $user, $docRoot, $phpVersion, $ssl),
            default => self::generateNginxPhpFpm($domain, $user, $docRoot, $phpVersion, $ssl),
        };
    }

    protected static function generateNginxPhpFpm(string $domain, string $user, string $docRoot, string $phpVersion, bool $ssl): string
    {
        $port = $ssl ? '443 ssl' : '80';
        $sslBlock = $ssl ? "
    ssl_certificate /etc/pki/tls/certs/{$domain}.crt;
    ssl_certificate_key /etc/pki/tls/private/{$domain}.key;" : '';

        return "server {
    listen {$port};
    server_name {$domain} www.{$domain};
    root {$docRoot};
    index index.php index.html;
    {$sslBlock}

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

    access_log /var/log/nginx/{$domain}_access.log;
    error_log /var/log/nginx/{$domain}_error.log;
}";
    }

    protected static function generateNginxApachePhpFpm(string $domain, string $user, string $docRoot, string $phpVersion, bool $ssl): string
    {
        $port = $ssl ? '443 ssl' : '80';
        $sslBlock = $ssl ? "
    ssl_certificate /etc/pki/tls/certs/{$domain}.crt;
    ssl_certificate_key /etc/pki/tls/private/{$domain}.key;" : '';

        return "server {
    listen {$port};
    server_name {$domain} www.{$domain};
    {$sslBlock}

    location / {
        proxy_pass http://127.0.0.1:8181;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location ~ \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)\$ {
        root {$docRoot};
        expires 30d;
    }

    access_log /var/log/nginx/{$domain}_access.log;
    error_log /var/log/nginx/{$domain}_error.log;
}

<VirtualHost *:8181>
    ServerName {$domain}
    DocumentRoot {$docRoot}

    <Directory {$docRoot}>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php\$>
        SetHandler \"proxy:unix:/var/run/php-fpm.sock|fcgi://localhost\"
    </FilesMatch>
</VirtualHost>";
    }

    protected static function generateNginxApachePhpCgi(string $domain, string $user, string $docRoot, string $phpVersion, bool $ssl): string
    {
        return self::generateNginxApachePhpFpm($domain, $user, $docRoot, $phpVersion, $ssl);
    }
}
