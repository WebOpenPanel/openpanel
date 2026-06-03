<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebStackService
{
    public const STACKS = [
        'nginx_phpfpm' => 'Nginx + PHP-FPM',
        'apache_phpfpm' => 'Apache + PHP-FPM',
        'nginx_apache' => 'Nginx â†’ Apache + PHP-FPM',
        'nginx_varnish_apache' => 'Nginx â†’ Varnish â†’ Apache + PHP-FPM',
    ];

    protected string $backupDir = '/usr/local/openpanel/backups/stack';
    protected string $nginxConfDir = '/etc/nginx/conf.d';
    protected string $apacheConfDir = '/etc/httpd/conf.d';
    protected string $varnishConfDir = '/etc/varnish';
    protected string $fpmPoolDir = '/etc/php-fpm.d';

    public function detectInstalledComponents(): array
    {
        return [
            'nginx' => $this->serviceExists('nginx'),
            'apache' => $this->serviceExists('httpd'),
            'varnish' => $this->serviceExists('varnish'),
            'php_fpm' => $this->serviceExists('php-fpm'),
        ];
    }

    public static function getActiveStack(): string
    {
        $settings = DB::connection('mysql')->table('web_stack_settings')->first();
        if ($settings && $settings->active_stack) {
            return $settings->active_stack;
        }

        // Fallback: check installer-written file
        $stackFile = '/etc/openpanel/web_stack';
        if (file_exists($stackFile)) {
            $fileStack = trim(file_get_contents($stackFile));
            if (isset(self::STACKS[$fileStack])) {
                return $fileStack;
            }
        }

        return 'nginx_phpfpm';
    }

    public function getStackConfig(): ?object
    {
        return DB::connection('mysql')->table('web_stack_settings')->first();
    }

    public function getAvailableStacks(): array
    {
        $components = $this->detectInstalledComponents();
        $available = [];

        foreach (self::STACKS as $key => $label) {
            $required = $this->getRequiredComponents($key);
            $missing = [];
            foreach ($required as $comp => $needed) {
                if ($needed && !($components[$comp] ?? false)) {
                    $missing[] = $comp;
                }
            }
            $available[$key] = [
                'label' => $label,
                'available' => empty($missing),
                'missing' => $missing,
                'required' => $required,
            ];
        }

        return $available;
    }

    public function installStackDependencies(string $stack): array
    {
        $packages = match ($stack) {
            'nginx_phpfpm' => ['nginx', 'php-fpm'],
            'apache_phpfpm' => ['httpd', 'php-fpm'],
            'nginx_apache' => ['nginx', 'httpd', 'php-fpm'],
            'nginx_varnish_apache' => ['nginx', 'httpd', 'php-fpm', 'varnish'],
            default => [],
        };

        $installed = [];
        $failed = [];

        foreach ($packages as $pkg) {
            $result = Process::run("rpm -q {$pkg} 2>/dev/null");
            if ($result->successful()) {
                $installed[] = "{$pkg} (already installed)";
                continue;
            }

            $result = Process::run("dnf -y install {$pkg} 2>&1");
            if ($result->successful()) {
                $installed[] = $pkg;
            } else {
                $failed[] = ['package' => $pkg, 'error' => $result->errorOutput()];
            }
        }

        return ['installed' => $installed, 'failed' => $failed];
    }

    public function validateStack(string $stack): array
    {
        $errors = [];
        $warnings = [];

        $components = $this->detectInstalledComponents();
        $required = $this->getRequiredComponents($stack);

        foreach ($required as $comp => $needed) {
            if ($needed && !($components[$comp] ?? false)) {
                $errors[] = "Required component not installed: {$comp}";
            }
        }

        switch ($stack) {
            case 'nginx_phpfpm':
                $result = Process::run('nginx -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'nginx config invalid: ' . $result->output();
                }
                $result = Process::run('php-fpm -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'php-fpm config invalid: ' . $result->output();
                }
                break;

            case 'apache_phpfpm':
                $result = Process::run('apachectl -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'apache config invalid: ' . $result->output();
                }
                $result = Process::run('php-fpm -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'php-fpm config invalid: ' . $result->output();
                }
                break;

            case 'nginx_apache':
                $result = Process::run('nginx -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'nginx config invalid: ' . $result->output();
                }
                $result = Process::run('apachectl -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'apache config invalid: ' . $result->output();
                }
                $result = Process::run('php-fpm -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'php-fpm config invalid: ' . $result->output();
                }
                break;

            case 'nginx_varnish_apache':
                $result = Process::run('nginx -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'nginx config invalid: ' . $result->output();
                }
                $result = Process::run('varnishd -C -f /etc/varnish/default.vcl 2>&1');
                if ($result->failed()) {
                    $errors[] = 'varnish VCL invalid: ' . $result->output();
                }
                $result = Process::run('apachectl -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'apache config invalid: ' . $result->output();
                }
                $result = Process::run('php-fpm -t 2>&1');
                if ($result->failed()) {
                    $errors[] = 'php-fpm config invalid: ' . $result->output();
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function switchStack(string $newStack, ?string $performedBy = null): array
    {
        if (!isset(self::STACKS[$newStack])) {
            return ['success' => false, 'message' => "Unknown stack: {$newStack}"];
        }

        $currentStack = $this->getActiveStack();
        if ($currentStack === $newStack) {
            return ['success' => false, 'message' => "Stack '{$newStack}' is already active."];
        }

        $historyId = DB::connection('mysql')->table('web_stack_history')->insertGetId([
            'from_stack' => $currentStack,
            'to_stack' => $newStack,
            'status' => 'in_progress',
            'performed_by' => $performedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->backupCurrentConfig();

            $validation = $this->validateStack($newStack);
            if (!$validation['valid']) {
                DB::connection('mysql')->table('web_stack_history')->where('id', $historyId)->update([
                    'status' => 'failed',
                    'validation_output' => implode("\n", $validation['errors']),
                    'updated_at' => now(),
                ]);
                return [
                    'success' => false,
                    'message' => 'Stack validation failed.',
                    'errors' => $validation['errors'],
                ];
            }

            $this->stopCurrentStackServices($currentStack);
            $this->generateStackConfigs($newStack);
            $this->startStackServices($newStack);

            $healthCheck = $this->testStackHealth($newStack);
            if (!$healthCheck['healthy']) {
                Log::warning("Stack switch health check failed, rolling back", $healthCheck);
                $this->rollbackStack();
                DB::connection('mysql')->table('web_stack_history')->where('id', $historyId)->update([
                    'status' => 'rolled_back',
                    'validation_output' => 'Health check failed: ' . implode(', ', $healthCheck['issues']),
                    'updated_at' => now(),
                ]);
                return [
                    'success' => false,
                    'message' => 'Stack switch failed health check, rolled back.',
                    'health' => $healthCheck,
                ];
            }

            DB::connection('mysql')->table('web_stack_settings')->updateOrInsert(
                ['id' => 1],
                [
                    'active_stack' => $newStack,
                    'previous_stack' => $currentStack,
                    'last_switch_at' => now(),
                    'last_switch_status' => 'success',
                    'updated_at' => now(),
                ]
            );

            DB::connection('mysql')->table('web_stack_history')->where('id', $historyId)->update([
                'status' => 'success',
                'updated_at' => now(),
            ]);

            $this->updateFirewallPorts($newStack);

            return [
                'success' => true,
                'message' => "Stack switched from '{$currentStack}' to '{$newStack}'.",
                'health' => $healthCheck,
            ];
        } catch (\Throwable $e) {
            Log::error("Stack switch failed: " . $e->getMessage());
            $this->rollbackStack();

            DB::connection('mysql')->table('web_stack_history')->where('id', $historyId)->update([
                'status' => 'error',
                'validation_output' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            return ['success' => false, 'message' => 'Switch failed: ' . $e->getMessage()];
        }
    }

    public function rollbackStack(): array
    {
        $settings = $this->getStackConfig();
        if (!$settings || !$settings->previous_stack) {
            return ['success' => false, 'message' => 'No previous stack to rollback to.'];
        }

        $backupDir = $this->backupDir;
        if (!is_dir($backupDir)) {
            return ['success' => false, 'message' => 'No backup found for rollback.'];
        }

        try {
            $this->stopCurrentStackServices($settings->active_stack);
            $this->restoreConfig($backupDir);
            $this->startStackServices($settings->previous_stack);

            DB::connection('mysql')->table('web_stack_settings')->where('id', 1)->update([
                'active_stack' => $settings->previous_stack,
                'previous_stack' => $settings->active_stack,
                'last_switch_at' => now(),
                'last_switch_status' => 'rolled_back',
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => "Rolled back to '{$settings->previous_stack}'.",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Rollback failed: ' . $e->getMessage()];
        }
    }

    public function reloadStack(string $stack): array
    {
        $services = $this->getStackServices($stack);
        $results = [];

        foreach ($services as $service) {
            $result = Process::run("systemctl reload {$service} 2>&1");
            $results[$service] = $result->successful() ? 'reloaded' : 'failed: ' . $result->output();
        }

        $allOk = !in_array(false, array_map(fn($v) => $v === 'reloaded', $results));
        return ['success' => $allOk, 'services' => $results];
    }

    public function getStackHealth(): array
    {
        $stack = $this->getActiveStack();
        return $this->testStackHealth($stack);
    }

    public function testStackWithDomain(string $stack, string $domain): array
    {
        $port = $this->getStackPort($stack);
        $result = Process::run("curl -sI -o /dev/null -w '%{{http_code}}' http://{$domain}:{$port}/ 2>/dev/null");
        $code = trim($result->output(), "'");

        return [
            'domain' => $domain,
            'port' => $port,
            'http_code' => $code,
            'success' => in_array($code, ['200', '301', '302']),
        ];
    }

    public function generateVhostForDomain(string $stack, string $username, string $domain, string $home): void
    {
        switch ($stack) {
            case 'nginx_phpfpm':
                $this->generateNginxPhpfpmVhost($username, $domain, $home);
                break;
            case 'apache_phpfpm':
                $this->generateApachePhpfpmVhost($username, $domain, $home);
                break;
            case 'nginx_apache':
                $this->generateApachePhpfpmVhost($username, $domain, $home, 8080);
                $this->generateNginxProxyVhost($username, $domain, $home, 8080);
                break;
            case 'nginx_varnish_apache':
                $this->generateApachePhpfpmVhost($username, $domain, $home, 8080);
                $this->generateVarnishVhost($username, $domain, 8080);
                $this->generateNginxProxyVhost($username, $domain, $home, 6081);
                break;
        }
    }

    public function removeVhostForDomain(string $stack, string $username, string $domain): void
    {
        switch ($stack) {
            case 'nginx_phpfpm':
                Process::run("sudo rm -f /etc/nginx/conf.d/users/{$username}.conf");
                break;
            case 'apache_phpfpm':
                Process::run("sudo rm -f /etc/httpd/conf.d/users/{$username}.conf");
                break;
            case 'nginx_apache':
                Process::run("sudo rm -f /etc/nginx/conf.d/users/{$username}.conf");
                Process::run("sudo rm -f /etc/httpd/conf.d/users/{$username}.conf");
                break;
            case 'nginx_varnish_apache':
                Process::run("sudo rm -f /etc/nginx/conf.d/users/{$username}.conf");
                Process::run("sudo rm -f /etc/httpd/conf.d/users/{$username}.conf");
                Process::run("sudo rm -f /etc/varnish/conf.d/users/{$username}.vcl");
                break;
        }
    }

    public function getSwitchHistory(int $limit = 20): array
    {
        return DB::connection('mysql')->table('web_stack_history')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Suspend a domain: stack-aware 403 block + Varnish ban.
     * Writes suspended vhost to /etc/nginx/conf.d/ (not users/) so it's always
     * included by nginx.conf. A specific server_name match beats server_name _.
     */
    public function suspendDomain(string $username, string $domain): array
    {
        $stack = $this->getActiveStack();
        $result = ['stack' => $stack, 'actions' => [], 'success' => true];

        // 1. Write suspended state marker
        Process::run("sudo mkdir -p /etc/openpanel/suspended");
        $tmp = tempnam(sys_get_temp_dir(), 'susp');
        file_put_contents($tmp, $username);
        Process::run("sudo cp " . escapeshellarg($tmp) . " /etc/openpanel/suspended/{$domain}");
        @unlink($tmp);
        $result['actions'][] = 'suspended_marker_written';

        // 2. Write nginx 403 vhost to conf.d/ directly (always included)
        //    This uses specific server_name which takes priority over catch-all server_name _
        $certPath = '/etc/pki/tls/certs/openpanel.crt';
        $keyPath = '/etc/pki/tls/private/openpanel.key';

        $sslBlock = '';
        if (file_exists($certPath) && file_exists($keyPath)) {
            $sslBlock = <<<NGINX

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$domain} www.{$domain};
    ssl_certificate {$certPath};
    ssl_certificate_key {$keyPath};
    return 403;
}
NGINX;
        }

        $suspendedVhost = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    return 403;
}
{$sslBlock}
NGINX;

        $suspendConfPath = "/etc/nginx/conf.d/suspended-{$username}.conf";
        $tmp = tempnam(sys_get_temp_dir(), 'sus');
        file_put_contents($tmp, $suspendedVhost);
        Process::run("sudo cp " . escapeshellarg($tmp) . " " . escapeshellarg($suspendConfPath));
        @unlink($tmp);
        $result['actions'][] = 'nginx_suspended_vhost_written';

        // 3. For apache-based stacks: replace Apache vhost with 403
        if (in_array($stack, ['apache_phpfpm', 'nginx_apache', 'nginx_varnish_apache'])) {
            $apacheVhostPath = "/etc/httpd/conf.d/users/{$username}.conf";
            $apacheBackupPath = "/etc/httpd/conf.d/users/{$username}.conf.suspended";

            $apachePort = ($stack === 'nginx_apache' || $stack === 'nginx_varnish_apache') ? 8080 : 80;

            $suspendedApache = <<<APACHE
<VirtualHost *:{$apachePort}>
    ServerName {$domain}
    ServerAlias www.{$domain}
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Require all denied
    </Directory>
    ErrorLog /dev/null
    CustomLog /dev/null combined
</VirtualHost>
APACHE;

            if (file_exists($apacheVhostPath)) {
                Process::run("sudo cp {$apacheVhostPath} {$apacheBackupPath}");
            }
            $tmp = tempnam(sys_get_temp_dir(), 'asus');
            file_put_contents($tmp, $suspendedApache);
            Process::run("sudo cp " . escapeshellarg($tmp) . " " . escapeshellarg($apacheVhostPath));
            @unlink($tmp);
            $result['actions'][] = 'apache_suspended_vhost_written';

            $apachectl = Process::run("apachectl -t 2>&1");
            if ($apachectl->successful()) {
                Process::run("systemctl reload httpd");
                $result['actions'][] = 'apache_reloaded';
            } else {
                if (file_exists($apacheBackupPath)) {
                    Process::run("sudo cp {$apacheBackupPath} {$apacheVhostPath}");
                }
                $result['actions'][] = 'apache_config_failed_rolled_back';
            }
        }

        // 4. For varnish stacks: ban all cached content for this domain
        if (in_array($stack, ['nginx_varnish_apache', 'nginx_apache'])) {
            Process::run("varnishadm 'ban req.http.host == \"{$domain}\"' 2>&1");
            Process::run("varnishadm 'ban req.http.host == \"www.{$domain}\"' 2>&1");
            $result['actions'][] = 'varnish_ban_executed';
        }

        // 5. Test and reload nginx
        $test = Process::run("nginx -t 2>&1");
        if ($test->successful()) {
            Process::run("systemctl reload nginx");
            $result['actions'][] = 'nginx_reloaded';
        } else {
            @unlink($suspendConfPath);
            $result['success'] = false;
            $result['actions'][] = 'nginx_config_failed_rolled_back';
        }

        return $result;
    }

    /**
     * Unsuspend a domain: remove 403 block + restore vhosts + purge Varnish.
     */
    public function unsuspendDomain(string $username, string $domain): array
    {
        $stack = $this->getActiveStack();
        $result = ['stack' => $stack, 'actions' => [], 'success' => true];

        // 1. Remove suspended state marker
        @unlink("/etc/openpanel/suspended/{$domain}");
        $result['actions'][] = 'suspended_marker_removed';

        // 2. Remove nginx suspended vhost
        $suspendConfPath = "/etc/nginx/conf.d/suspended-{$username}.conf";
        @unlink($suspendConfPath);
        $result['actions'][] = 'nginx_suspended_vhost_removed';

        // 3. Restore Apache vhost for apache-based stacks
        if (in_array($stack, ['apache_phpfpm', 'nginx_apache', 'nginx_varnish_apache'])) {
            $apacheVhostPath = "/etc/httpd/conf.d/users/{$username}.conf";
            $apacheBackupPath = "/etc/httpd/conf.d/users/{$username}.conf.suspended";

            if (file_exists($apacheBackupPath)) {
                Process::run("sudo cp {$apacheBackupPath} {$apacheVhostPath}");
                Process::run("sudo rm -f {$apacheBackupPath}");
                $result['actions'][] = 'apache_vhost_restored';
            } else {
                // No backup — regenerate from account data
                $account = DB::connection('mysql')->table('accounts')->where('username', $username)->first();
                if ($account) {
                    $home = "/home/{$username}";
                    $apachePort = ($stack === 'nginx_apache' || $stack === 'nginx_varnish_apache') ? 8080 : 80;
                    $this->generateApachePhpfpmVhost($username, $domain, $home, $apachePort);
                    $result['actions'][] = 'apache_vhost_regenerated';
                }
            }

            // If Apache config fails, regenerate as fallback
            $apachectl = Process::run("apachectl -t 2>&1");
            if ($apachectl->failed()) {
                $account = DB::connection('mysql')->table('accounts')->where('username', $username)->first();
                if ($account) {
                    $home = "/home/{$username}";
                    $apachePort = ($stack === 'nginx_apache' || $stack === 'nginx_varnish_apache') ? 8080 : 80;
                    $this->generateApachePhpfpmVhost($username, $domain, $home, $apachePort);
                    $result['actions'][] = 'apache_vhost_regenerated_fallback';
                }
            }
        }

        // 4. For varnish stacks: purge stale cache
        if (in_array($stack, ['nginx_varnish_apache', 'nginx_apache'])) {
            Process::run("varnishadm 'ban req.http.host == \"{$domain}\"' 2>&1");
            Process::run("varnishadm 'ban req.http.host == \"www.{$domain}\"' 2>&1");
            $result['actions'][] = 'varnish_cache_purged';
        }

        // 5. Test and reload services
        $reloaded = [];

        if (in_array($stack, ['apache_phpfpm', 'nginx_apache', 'nginx_varnish_apache'])) {
            $apachectl = Process::run("apachectl -t 2>&1");
            if ($apachectl->successful()) {
                Process::run("systemctl reload httpd");
                $reloaded[] = 'httpd';
            } else {
                $result['success'] = false;
                $result['actions'][] = 'apache_config_failed';
            }
        }

        if (in_array($stack, ['nginx_phpfpm', 'nginx_apache', 'nginx_varnish_apache'])) {
            $nginxt = Process::run("nginx -t 2>&1");
            if ($nginxt->successful()) {
                Process::run("systemctl reload nginx");
                $reloaded[] = 'nginx';
            } else {
                $result['success'] = false;
                $result['actions'][] = 'nginx_config_failed';
            }
        }

        $result['actions'][] = 'services_reloaded: ' . implode(',', $reloaded);

        return $result;
    }

    /**
     * Purge Varnish cache for a domain.
     */
    public function purgeVarnishCache(string $domain): bool
    {
        Process::run("varnishadm 'ban req.http.host == \"{$domain}\"' 2>&1");
        Process::run("varnishadm 'ban req.http.host == \"www.{$domain}\"' 2>&1");
        return true;
    }

    protected function generateNginxPhpfpmVhost(string $username, string $domain, string $home): void
    {
        $vhost = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};

    root {$home}/public_html;
    index index.html index.htm index.php;

    access_log {$home}/logs/nginx/access.log;
    error_log {$home}/logs/nginx/error.log;

    client_max_body_size 64M;

    # Symlink protection: only follow symlinks owned by the target user
    disable_symlinks if_not_owner from={$home}/public_html;

    # Security headers
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/openpanel-php-user-{$username}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Block access to hidden files (except .well-known)
    location ~ /\.(?!well-known).* { deny all; }

    # Block access to sensitive files
    location ~* \.(env|bak|sql|log|conf|ini|sh|py)\$ { deny all; }

    # Block access to sensitive paths
    location ~ ^/(private|backups|logs|\.openpanel)/ { deny all; }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
NGINX;

        Process::run("sudo mkdir -p /etc/nginx/conf.d/users");
        $tmp = tempnam(sys_get_temp_dir(), 'ngx');
        file_put_contents($tmp, $vhost);
        Process::run("sudo cp " . escapeshellarg($tmp) . " /etc/nginx/conf.d/users/{$username}.conf");
        @unlink($tmp);
    }

    protected function generateApachePhpfpmVhost(string $username, string $domain, string $home, int $port = 80): void
    {
        $vhost = <<<APACHE
<VirtualHost *:{$port}>
    ServerName {$domain}
    ServerAlias www.{$domain}
    DocumentRoot {$home}/public_html

    # Symlink protection: only follow symlinks owned by the same user
    <Directory {$home}/public_html>
        AllowOverride All
        Require all granted
        Options -Indexes +SymLinksIfOwnerMatch
    </Directory>

    # Block access to sensitive directories
    <DirectoryMatch "^{$home}/(private|backups|logs|\.openpanel)">
        Require all denied
    </DirectoryMatch>

    # Block access to hidden files
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    # Block access to sensitive file types
    <FilesMatch "\.(env|bak|sql|log|conf|ini|sh|py)$">
        Require all denied
    </FilesMatch>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/openpanel-php-user-{$username}.sock|fcgi://localhost"
    </FilesMatch>

    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog {$home}/logs/apache/error.log
    CustomLog {$home}/logs/apache/access.log combined
</VirtualHost>
APACHE;

        Process::run("sudo mkdir -p /etc/httpd/conf.d/users");
        $tmp = tempnam(sys_get_temp_dir(), 'apa');
        file_put_contents($tmp, $vhost);
        Process::run("sudo cp " . escapeshellarg($tmp) . " /etc/httpd/conf.d/users/{$username}.conf");
        @unlink($tmp);
    }

    protected function generateNginxProxyVhost(string $username, string $domain, string $home, int $backendPort): void
    {
        $vhost = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};

    access_log {$home}/logs/nginx/access.log;
    error_log {$home}/logs/nginx/error.log;

    client_max_body_size 64M;

    # Security headers
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location / {
        proxy_pass http://127.0.0.1:{$backendPort};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_pass_header X-Cache;
        proxy_pass_header Via;
        proxy_pass_header Age;
        proxy_pass_header X-Varnish;
    }

    # Block access to hidden files
    location ~ /\.(?!well-known).* { deny all; }

    # Block access to sensitive files
    location ~* \.(env|bak|sql|log|conf|ini|sh|py)\$ { deny all; }

    # Block access to sensitive paths
    location ~ ^/(private|backups|logs|\.openpanel)/ { deny all; }
}
NGINX;

        Process::run("sudo mkdir -p /etc/nginx/conf.d/users");
        $tmp = tempnam(sys_get_temp_dir(), 'ngx');
        file_put_contents($tmp, $vhost);
        Process::run("sudo cp " . escapeshellarg($tmp) . " /etc/nginx/conf.d/users/{$username}.conf");
        @unlink($tmp);
    }

    protected function generateVarnishVhost(string $username, string $domain, int $backendPort): void
    {
        $vcl = <<<VCL
sub vcl_recv {
    if (req.http.host == "{$domain}" || req.http.host == "www.{$domain}") {
        set req.backend_hint = default;

        if (req.url ~ "wp-admin" || req.url ~ "wp-login.php" || req.url ~ "xmlrpc.php") {
            return (pass);
        }
        if (req.method == "POST") {
            return (pass);
        }
        if (req.http.Authorization) {
            return (pass);
        }
        if (req.http.Cookie ~ "wordpress_logged_in|wp-postpass|comment_author|woocommerce") {
            return (pass);
        }
        return (hash);
    }
}

sub vcl_backend_response {
    if (bereq.http.host == "{$domain}" || bereq.http.host == "www.{$domain}") {
        if (bereq.url ~ "wp-admin" || bereq.url ~ "wp-login.php") {
            set beresp.uncacheable = true;
            set beresp.ttl = 0s;
        }
        if (beresp.http.Set-Cookie ~ "wordpress_logged_in|wp-postpass|comment_author") {
            set beresp.uncacheable = true;
            set beresp.ttl = 0s;
        }
    }
}
VCL;

        Process::run("sudo mkdir -p /etc/varnish/conf.d/users");
        $tmp = tempnam(sys_get_temp_dir(), 'vcl');
        file_put_contents($tmp, $vcl);
        Process::run("sudo cp " . escapeshellarg($tmp) . " /etc/varnish/conf.d/users/{$username}.vcl");
        @unlink($tmp);
    }

    protected function generateStackConfigs(string $stack): void
    {
        match ($stack) {
            'nginx_phpfpm' => $this->configureNginxPhpfpm(),
            'apache_phpfpm' => $this->configureApachePhpfpm(),
            'nginx_apache' => $this->configureNginxApache(),
            'nginx_varnish_apache' => $this->configureNginxVarnishApache(),
        };
    }

    protected function configureNginxPhpfpm(): void
    {
        Process::run("systemctl enable nginx php-fpm");
    }

    protected function configureApachePhpfpm(): void
    {
        Process::run("sudo mkdir -p /etc/httpd/conf.d/users");

        $include = 'IncludeOptional conf.d/users/*.conf';
        $result = Process::run("sudo grep -q 'conf.d/users' /etc/httpd/conf/httpd.conf 2>/dev/null");
        if ($result->failed()) {
            Process::run("sudo bash -c " . escapeshellarg("echo '{$include}' >> /etc/httpd/conf/httpd.conf"));
        }

        Process::run("sudo usermod -aG nginx apache 2>/dev/null");

        if (!Process::run("sudo grep -q 'include=/etc/php-fpm.d/users' /etc/php-fpm.conf 2>/dev/null")->successful()) {
            Process::run("sudo bash -c " . escapeshellarg("echo 'include=/etc/php-fpm.d/users/*.conf' >> /etc/php-fpm.conf"));
        }

        Process::run("systemctl enable httpd php-fpm");
    }

    protected function configureNginxApache(): void
    {
        $this->configureApachePhpfpm();
        Process::run("systemctl enable nginx");
    }

    protected function configureNginxVarnishApache(): void
    {
        $this->configureNginxApache();

        $vclPath = '/etc/varnish/default.vcl';
        if (!file_exists($vclPath)) {
            $defaultVcl = <<<VCL
vcl 4.0;

backend default {
    .host = "127.0.0.1";
    .port = "8080";
}

sub vcl_recv {
    set req.http.X-Forwarded-For = client.ip;
}
VCL;
            $tmp = tempnam(sys_get_temp_dir(), 'dvcl');
            file_put_contents($tmp, $defaultVcl);
            Process::run("sudo cp " . escapeshellarg($tmp) . " " . escapeshellarg($vclPath));
            @unlink($tmp);
        }

        Process::run("systemctl enable varnish");
    }

    protected function stopCurrentStackServices(string $stack): void
    {
        $services = $this->getStackServices($stack);
        foreach (array_reverse($services) as $service) {
            Process::run("systemctl stop {$service} 2>/dev/null");
        }
    }

    protected function startStackServices(string $stack): void
    {
        $services = $this->getStackServices($stack);
        foreach ($services as $service) {
            Process::run("systemctl start {$service} 2>/dev/null");
        }
    }

    public function getStackServices(string $stack): array
    {
        return match ($stack) {
            'nginx_phpfpm' => ['php-fpm', 'nginx'],
            'apache_phpfpm' => ['php-fpm', 'httpd'],
            'nginx_apache' => ['php-fpm', 'httpd', 'nginx'],
            'nginx_varnish_apache' => ['php-fpm', 'httpd', 'varnish', 'nginx'],
            default => [],
        };
    }

    protected function getStackPort(string $stack): int
    {
        return 80;
    }

    protected function getRequiredComponents(string $stack): array
    {
        return match ($stack) {
            'nginx_phpfpm' => ['nginx' => true, 'apache' => false, 'varnish' => false, 'php_fpm' => true],
            'apache_phpfpm' => ['nginx' => false, 'apache' => true, 'varnish' => false, 'php_fpm' => true],
            'nginx_apache' => ['nginx' => true, 'apache' => true, 'varnish' => false, 'php_fpm' => true],
            'nginx_varnish_apache' => ['nginx' => true, 'apache' => true, 'varnish' => true, 'php_fpm' => true],
            default => [],
        };
    }

    protected function serviceExists(string $service): bool
    {
        $result = Process::run("systemctl list-unit-files {$service}.service 2>/dev/null | grep -q {$service}");
        return $result->successful();
    }

    protected function backupCurrentConfig(): void
    {
        $backupDir = $this->backupDir . '/' . date('Y-m-d_His');
        Process::run("sudo mkdir -p {$backupDir}");

        $dirs = [
            '/etc/nginx/conf.d' => 'nginx_conf_d',
            '/etc/httpd/conf.d' => 'httpd_conf_d',
            '/etc/varnish' => 'varnish',
            '/etc/php-fpm.d' => 'php_fpm_d',
        ];

        foreach ($dirs as $src => $dest) {
            if (is_dir($src)) {
                Process::run("sudo cp -r {$src} {$backupDir}/{$dest} 2>/dev/null");
            }
        }

        Process::run("sudo ln -sfn {$backupDir} {$this->backupDir}/latest");
    }

    protected function restoreConfig(string $backupDir): void
    {
        $latest = $backupDir . '/latest';
        if (!is_dir($latest)) {
            throw new \RuntimeException('No backup found for restore');
        }

        $map = [
            "{$latest}/nginx_conf_d" => '/etc/nginx/conf.d',
            "{$latest}/httpd_conf_d" => '/etc/httpd/conf.d',
            "{$latest}/varnish" => '/etc/varnish',
            "{$latest}/php_fpm_d" => '/etc/php-fpm.d',
        ];

        foreach ($map as $src => $dest) {
            if (is_dir($src)) {
                Process::run("sudo rm -rf {$dest} && sudo cp -r {$src} {$dest} 2>/dev/null");
            }
        }
    }

    protected function testStackHealth(string $stack): array
    {
        $issues = [];
        $services = $this->getStackServices($stack);

        foreach ($services as $service) {
            $result = Process::run("systemctl is-active {$service} 2>/dev/null");
            if (trim($result->output()) !== 'active') {
                $issues[] = "{$service} is not active";
            }
        }

        $result = Process::run("curl -sI -o /dev/null -w '%{{http_code}}' http://localhost/ 2>/dev/null");
        $code = trim($result->output(), "'");
        if (!in_array($code, ['200', '301', '302', '403'])) {
            $issues[] = "HTTP on port 80 returned {$code}";
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'services' => array_map(fn($s) => [
                'name' => $s,
                'active' => trim((Process::run("systemctl is-active {$s} 2>/dev/null"))->output()) === 'active',
            ], $services),
        ];
    }

    protected function updateFirewallPorts(string $stack): void
    {
        $ports = match ($stack) {
            'nginx_phpfpm' => ['80/tcp', '443/tcp'],
            'apache_phpfpm' => ['80/tcp', '443/tcp'],
            'nginx_apache' => ['80/tcp', '443/tcp'],
            'nginx_varnish_apache' => ['80/tcp', '443/tcp'],
            default => [],
        };

        foreach ($ports as $port) {
            Process::run("firewall-cmd --permanent --add-service=http 2>/dev/null");
            Process::run("firewall-cmd --permanent --add-service=https 2>/dev/null");
        }

        Process::run("firewall-cmd --reload 2>/dev/null");
    }

    public function initializeSettings(): void
    {
        $exists = DB::connection('mysql')->table('web_stack_settings')->where('id', 1)->exists();
        if (!$exists) {
            DB::connection('mysql')->table('web_stack_settings')->insert([
                'id' => 1,
                'active_stack' => 'nginx_phpfpm',
                'previous_stack' => null,
                'nginx_public_port' => 80,
                'apache_backend_port' => 8080,
                'varnish_port' => 6081,
                'php_fpm_mode' => 'socket',
                'php_fpm_socket_path' => '/run/php-fpm/www.sock',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
