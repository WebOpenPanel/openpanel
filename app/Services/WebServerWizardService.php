<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class WebServerWizardService
{
    protected static string $wizardStateFile = '/usr/local/openpanel/.conf/wizard_state.json';

    public static function getState(): array
    {
        if (!file_exists(self::$wizardStateFile)) {
            return ['completed' => false, 'step' => 1, 'config' => []];
        }
        return json_decode(file_get_contents(self::$wizardStateFile), true) ?: ['completed' => false, 'step' => 1, 'config' => []];
    }

    public static function saveState(array $state): void
    {
        File::ensureDirectoryExists(dirname(self::$wizardStateFile));
        file_put_contents(self::$wizardStateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    public static function getAvailableServers(): array
    {
        return [
            'nginx_apache_php-fpm' => ['name' => 'Nginx + Apache + PHP-FPM', 'description' => 'Nginx reverse proxy with Apache backend and PHP-FPM'],
            'nginx_apache_php-cgi' => ['name' => 'Nginx + Apache + PHP-CGI', 'description' => 'Nginx reverse proxy with Apache backend and PHP-CGI'],
            'nginx_php-fpm' => ['name' => 'Nginx + PHP-FPM', 'description' => 'Nginx with PHP-FPM (no Apache)'],
            'nginx_varnish_apache_php-fpm' => ['name' => 'Nginx + Varnish + Apache + PHP-FPM', 'description' => 'Full stack with Varnish cache'],
            'nginx_varnish_apache_php-cgi' => ['name' => 'Nginx + Varnish + Apache + PHP-CGI', 'description' => 'Full stack with Varnish and PHP-CGI'],
            'openlitespeed' => ['name' => 'OpenLiteSpeed', 'description' => 'OpenLiteSpeed web server'],
        ];
    }

    public static function getAvailablePhpVersions(): array
    {
        return PhpService::getInstalledVersions();
    }

    public static function getAvailableMysqlVersions(): array
    {
        $output = ShellService::exec('mysql --version 2>/dev/null');
        $versions = [];
        if (preg_match('/(\d+\.\d+\.\d+)/', $output, $m)) {
            $versions[] = ['version' => $m[1], 'type' => stripos($output, 'mariadb') !== false ? 'MariaDB' : 'MySQL'];
        }
        return $versions;
    }

    public static function applyStep1(array $data): array
    {
        $state = self::getState();
        $state['step'] = 1;
        $state['config']['hostname'] = $data['hostname'] ?? gethostname();
        $state['config']['nameserver1'] = $data['nameserver1'] ?? '';
        $state['config']['nameserver2'] = $data['nameserver2'] ?? '';
        self::saveState($state);

        if (!empty($state['config']['hostname'])) {
            ShellService::exec("hostnamectl set-hostname {$state['config']['hostname']}");
        }

        return ['success' => true, 'message' => 'Hostname configured.'];
    }

    public static function applyStep2(array $data): array
    {
        $state = self::getState();
        $state['step'] = 2;
        $state['config']['web_server'] = $data['web_server'] ?? 'nginx_php-fpm';
        self::saveState($state);
        return ['success' => true, 'message' => 'Web server selected.'];
    }

    public static function applyStep3(array $data): array
    {
        $state = self::getState();
        $state['step'] = 3;
        $state['config']['php_version'] = $data['php_version'] ?? '8.3';
        $state['config']['php_settings'] = $data['php_settings'] ?? [];
        self::saveState($state);
        return ['success' => true, 'message' => 'PHP configured.'];
    }

    public static function applyStep4(array $data): array
    {
        $state = self::getState();
        $state['step'] = 4;
        $state['config']['mysql_root_password'] = $data['mysql_root_password'] ?? '';
        $state['config']['mysql_version'] = $data['mysql_version'] ?? '';
        self::saveState($state);
        return ['success' => true, 'message' => 'MySQL configured.'];
    }

    public static function finishWizard(): array
    {
        $state = self::getState();
        $state['completed'] = true;
        $state['completed_at'] = now()->toDateTimeString();
        self::saveState($state);

        return ['success' => true, 'message' => 'Web server wizard completed.'];
    }

    public static function resetWizard(): array
    {
        self::saveState(['completed' => false, 'step' => 1, 'config' => []]);
        return ['success' => true, 'message' => 'Wizard reset.'];
    }
}
