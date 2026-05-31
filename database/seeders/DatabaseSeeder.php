<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Service;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Package::create([
            'name' => 'Default',
            'description' => 'Default hosting package',
            'disk_space_mb' => 5120,
            'bandwidth_mb' => 51200,
            'max_domains' => 5,
            'max_subdomains' => 10,
            'max_email_accounts' => 25,
            'max_databases' => 10,
            'max_ftp_accounts' => 10,
            'max_parked_domains' => 5,
            'shell_access' => false,
            'ssl_enabled' => true,
            'max_cron_jobs' => 10,
            'php_version' => '8.2',
            'web_server' => 'apache',
        ]);

        Package::create([
            'name' => 'Premium',
            'description' => 'Premium hosting package',
            'disk_space_mb' => 20480,
            'bandwidth_mb' => 204800,
            'max_domains' => 25,
            'max_subdomains' => 50,
            'max_email_accounts' => 100,
            'max_databases' => 50,
            'max_ftp_accounts' => 50,
            'max_parked_domains' => 25,
            'shell_access' => true,
            'ssl_enabled' => true,
            'max_cron_jobs' => 50,
            'php_version' => '8.3',
            'web_server' => 'nginx',
        ]);

        Package::create([
            'name' => 'Starter',
            'description' => 'Starter hosting package',
            'disk_space_mb' => 1024,
            'bandwidth_mb' => 10240,
            'max_domains' => 1,
            'max_subdomains' => 3,
            'max_email_accounts' => 5,
            'max_databases' => 2,
            'max_ftp_accounts' => 2,
            'max_parked_domains' => 0,
            'shell_access' => false,
            'ssl_enabled' => true,
            'max_cron_jobs' => 3,
            'php_version' => '8.2',
            'web_server' => 'apache',
        ]);

        $services = [
            ['name' => 'httpd', 'display_name' => 'Apache HTTP Server', 'service_name' => 'httpd', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'nginx', 'display_name' => 'Nginx Web Server', 'service_name' => 'nginx', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'mysqld', 'display_name' => 'MySQL Database', 'service_name' => 'mysqld', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'postfix', 'display_name' => 'Postfix Mail Server', 'service_name' => 'postfix', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'dovecot', 'display_name' => 'Dovecot IMAP/POP3', 'service_name' => 'dovecot', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'named', 'display_name' => 'BIND DNS Server', 'service_name' => 'named', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'sshd', 'display_name' => 'SSH Server', 'service_name' => 'sshd', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'pure-ftpd', 'display_name' => 'Pure-FTPd', 'service_name' => 'pure-ftpd', 'status' => 'stopped', 'type' => 'systemd'],
            ['name' => 'crond', 'display_name' => 'Cron Daemon', 'service_name' => 'crond', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'firewalld', 'display_name' => 'Firewall Daemon', 'service_name' => 'firewalld', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'php-fpm-82', 'display_name' => 'PHP-FPM 8.2', 'service_name' => 'php-fpm-82', 'status' => 'running', 'type' => 'systemd'],
            ['name' => 'php-fpm-83', 'display_name' => 'PHP-FPM 8.3', 'service_name' => 'php-fpm-83', 'status' => 'stopped', 'type' => 'systemd'],
            ['name' => 'redis', 'display_name' => 'Redis Cache', 'service_name' => 'redis', 'status' => 'stopped', 'type' => 'systemd'],
            ['name' => 'memcached', 'display_name' => 'Memcached', 'service_name' => 'memcached', 'status' => 'stopped', 'type' => 'systemd'],
            ['name' => 'elasticsearch', 'display_name' => 'Elasticsearch', 'service_name' => 'elasticsearch', 'status' => 'stopped', 'type' => 'systemd'],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
