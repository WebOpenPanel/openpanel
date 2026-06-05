<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\WordPressSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DomainSslService
{
    public function issue(string $domain, ?string $email = null, bool $redirectHttps = false): array
    {
        $domain = $this->normalizeDomain($domain);
        $account = $this->findAccount($domain);
        $paths = $this->certPaths($domain);

        $this->ensureCertbot();
        $this->ensureWebroot($account['docroot']);

        $emailArgs = $email
            ? '--email ' . escapeshellarg($email)
            : '--register-unsafely-without-email';

        $command = sprintf(
            'sudo certbot certonly --webroot -w %s -d %s --non-interactive --agree-tos %s --keep-until-expiring 2>&1',
            escapeshellarg($account['docroot']),
            escapeshellarg($domain),
            $emailArgs
        );

        $issued = Process::timeout(300)->run($command);
        if ($issued->failed() || !$this->pathExists($paths['cert']) || !$this->pathExists($paths['key'])) {
            $error = $this->safeOutput($issued->output() ?: $issued->errorOutput());
            $this->recordFailure($domain, 'issuance_failed', $error);
            throw new RuntimeException('Certificate issuance failed: ' . $error);
        }

        $install = $this->installExistingCertificate($domain, $redirectHttps);
        $hook = $this->ensureRenewalHook();
        $status = $this->status($domain);

        return [
            'success' => true,
            'domain' => $domain,
            'certificate_issued' => true,
            'vhost_updated' => $install['success'],
            'nginx_reload_status' => $install['nginx_reload_status'],
            'expires_at' => $status['expires_at'],
            'auto_renew' => $hook['auto_renew'],
            'force_https' => $status['force_https'],
            'wordpress_https_updated' => $install['wordpress_https_updated'] ?? false,
            'warning' => $redirectHttps ? null : 'HTTPS redirect disabled; HTTP remains available.',
        ];
    }

    public function renew(string $domain, ?bool $forceHttps = null): array
    {
        $domain = $this->normalizeDomain($domain);

        $this->ensureRenewalHook();
        $renew = Process::timeout(300)->run(
            'sudo certbot renew --cert-name ' . escapeshellarg($domain) . ' --deploy-hook ' .
            escapeshellarg('/etc/letsencrypt/renewal-hooks/deploy/openpanel-domain-ssl.sh') . ' 2>&1'
        );

        $paths = $this->certPaths($domain);
        if ($renew->failed() || !$this->pathExists($paths['cert']) || !$this->pathExists($paths['key'])) {
            $error = $this->safeOutput($renew->output() ?: $renew->errorOutput());
            $this->recordFailure($domain, 'renewal_failed', $error);
            throw new RuntimeException('Certificate renewal failed: ' . $error);
        }

        $install = $this->installExistingCertificate($domain, $forceHttps);
        $status = $this->status($domain);

        return [
            'success' => true,
            'domain' => $domain,
            'certificate_issued' => true,
            'vhost_updated' => $install['success'],
            'nginx_reload_status' => $install['nginx_reload_status'],
            'expires_at' => $status['expires_at'],
            'force_https' => $status['force_https'],
            'wordpress_https_updated' => $install['wordpress_https_updated'] ?? false,
            'warning' => 'Renewal attempted; certbot may keep the existing certificate until renewal is due.',
        ];
    }

    public function setForceHttps(string $domain, bool $forceHttps): array
    {
        $domain = $this->normalizeDomain($domain);
        $paths = $this->certPaths($domain);

        if (!$this->pathExists($paths['cert']) || !$this->pathExists($paths['key'])) {
            throw new RuntimeException('Force HTTPS requires an installed valid certificate.');
        }

        $install = $this->installExistingCertificate($domain, $forceHttps);
        $status = $this->status($domain);

        return [
            'success' => $install['success'],
            'domain' => $domain,
            'force_https' => $status['force_https'],
            'vhost_updated' => $install['success'],
            'nginx_reload_status' => $install['nginx_reload_status'],
            'wordpress_https_updated' => $install['wordpress_https_updated'] ?? false,
            'warning' => $forceHttps ? null : 'Force HTTPS disabled; HTTP remains available.',
        ];
    }

    public function status(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $paths = $this->certPaths($domain);
        $record = SslCertificate::where('domain', $domain)->where('type', 'letsencrypt')->latest()->first();
        $certExists = $this->pathExists($paths['cert']);
        $keyExists = $this->pathExists($paths['key']);
        $cert = $certExists ? @openssl_x509_parse($this->readTextFile($paths['cert'])) : false;
        $expiresAt = $cert && isset($cert['validTo_time_t'])
            ? Carbon::createFromTimestamp($cert['validTo_time_t'])
            : ($record?->expires_at ? Carbon::parse($record->expires_at) : null);

        $vhostInstalled = $this->isVhostInstalled($domain, $paths['cert'], $paths['key']);
        $forceHttps = $vhostInstalled
            ? $this->isForceHttpsInstalled($domain)
            : $this->storedForceHttps($domain);

        return [
            'domain' => $domain,
            'ssl_enabled' => $certExists && $keyExists && $vhostInstalled,
            'provider' => $certExists ? 'letsencrypt' : ($record?->type ?? null),
            'expires_at' => $expiresAt?->toIso8601String(),
            'days_remaining' => $expiresAt ? (int) floor(now()->diffInDays($expiresAt, false)) : null,
            'certificate_subject' => $cert['subject']['CN'] ?? null,
            'issuer' => $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? null),
            'auto_renew' => (bool) ($record?->auto_renew ?? true),
            'force_https' => $forceHttps,
            'vhost_installed' => $vhostInstalled,
            'cert_path' => $certExists ? $paths['cert'] : null,
            'key_path' => $keyExists ? $paths['key'] : null,
            'last_renewal_status' => $record?->last_renewal_status,
            'last_error' => $record?->last_error,
        ];
    }

    public function installExistingCertificate(string $domain, ?bool $redirectHttps = null): array
    {
        $domain = $this->normalizeDomain($domain);
        $account = $this->findAccount($domain);
        $paths = $this->certPaths($domain);
        $redirectHttps = $redirectHttps ?? $this->storedForceHttps($domain);

        if (!$this->pathExists($paths['cert']) || !$this->pathExists($paths['key'])) {
            throw new RuntimeException("Certificate files are missing for {$domain}.");
        }

        $stack = WebStackService::getActiveStack();
        $vhost = $this->generateNginxVhost(
            $stack,
            $account['username'],
            $domain,
            $account['home'],
            $account['docroot'],
            $paths['cert'],
            $paths['key'],
            $redirectHttps
        );

        $install = $this->safeInstallNginxVhost($account['username'], $vhost);
        if (!$install['success']) {
            $this->recordFailure($domain, 'vhost_install_failed', $install['error'] ?? 'nginx validation failed');
            return $install;
        }

        $this->recordSuccess($domain, $paths, $install, $redirectHttps);
        $this->purgeVarnish($domain);
        $install['wordpress_https_updated'] = $this->syncWordPressHttps($domain, $redirectHttps);

        return $install;
    }

    public function certificatePathsForDomain(string $domain): ?array
    {
        $domain = $this->normalizeDomain($domain);
        $paths = $this->certPaths($domain);

        return $this->pathExists($paths['cert']) && $this->pathExists($paths['key']) ? $paths : null;
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim(preg_replace('#^https?://#', '', $domain)));
        $domain = trim($domain, "/ \t\n\r\0\x0B.");

        if (!preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new RuntimeException('Invalid domain name.');
        }

        return $domain;
    }

    protected function findAccount(string $domain): array
    {
        $account = DB::connection('mysql')->table('accounts')->where('domain', $domain)->first();

        if (!$account && str_starts_with($domain, 'www.')) {
            $account = DB::connection('mysql')->table('accounts')->where('domain', substr($domain, 4))->first();
        }

        if (!$account) {
            throw new RuntimeException("No hosting account found for {$domain}.");
        }

        $home = "/home/{$account->username}";
        $docroot = "{$home}/public_html";

        return [
            'username' => $account->username,
            'home' => $home,
            'docroot' => $docroot,
        ];
    }

    protected function certPaths(string $domain): array
    {
        $live = rtrim(config('openpanel.paths.letsencrypt_live', '/etc/letsencrypt/live'), '/');

        return [
            'cert' => "{$live}/{$domain}/fullchain.pem",
            'key' => "{$live}/{$domain}/privkey.pem",
        ];
    }

    protected function ensureCertbot(): void
    {
        if (Process::run('command -v certbot >/dev/null 2>&1')->successful()) {
            return;
        }

        $install = Process::timeout(300)->run('sudo dnf -y install certbot python3-certbot-nginx 2>&1');
        if ($install->failed()) {
            throw new RuntimeException('certbot is not installed and automatic install failed.');
        }
    }

    protected function ensureWebroot(string $docroot): void
    {
        Process::run('sudo mkdir -p ' . escapeshellarg("{$docroot}/.well-known/acme-challenge"));
    }

    protected function generateNginxVhost(
        string $stack,
        string $username,
        string $domain,
        string $home,
        string $docroot,
        string $certPath,
        string $keyPath,
        bool $redirectHttps
    ): string {
        if ($stack === 'nginx_phpfpm') {
            return $this->generatePhpFpmVhost($username, $domain, $home, $docroot, $certPath, $keyPath, $redirectHttps);
        }

        $backendPort = match ($stack) {
            'nginx_varnish_apache' => 6081,
            'nginx_apache' => 8080,
            default => 6081,
        };

        return $this->generateProxyVhost($domain, $home, $docroot, $certPath, $keyPath, $backendPort, $redirectHttps);
    }

    protected function generatePhpFpmVhost(
        string $username,
        string $domain,
        string $home,
        string $docroot,
        string $certPath,
        string $keyPath,
        bool $redirectHttps
    ): string {
        $httpBody = $redirectHttps
            ? $this->acmeLocation($docroot) . "\n\n    location / {\n        return 301 https://\$host\$request_uri;\n    }"
            : $this->phpFpmLocations($username, $home, $docroot);

        $httpsBody = $this->phpFpmLocations($username, $home, $docroot);
        $tlsDefaults = $this->tlsDefaults();

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};

    root {$docroot};
    index index.html index.htm index.php;

    access_log {$home}/logs/nginx/access.log;
    error_log {$home}/logs/nginx/error.log;

    client_max_body_size 64M;
    disable_symlinks if_not_owner from={$docroot};

    {$httpBody}
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$domain} www.{$domain};

    root {$docroot};
    index index.html index.htm index.php;

    ssl_certificate {$certPath};
    ssl_certificate_key {$keyPath};
    {$tlsDefaults}

    access_log {$home}/logs/nginx/access.log;
    error_log {$home}/logs/nginx/error.log;

    client_max_body_size 64M;
    disable_symlinks if_not_owner from={$docroot};

    {$httpsBody}
}
NGINX;
    }

    protected function generateProxyVhost(
        string $domain,
        string $home,
        string $docroot,
        string $certPath,
        string $keyPath,
        int $backendPort,
        bool $redirectHttps
    ): string {
        $httpBody = $redirectHttps
            ? $this->acmeLocation($docroot) . "\n\n    location / {\n        return 301 https://\$host\$request_uri;\n    }"
            : $this->proxyLocations($docroot, $backendPort);

        $httpsBody = $this->proxyLocations($docroot, $backendPort);
        $tlsDefaults = $this->tlsDefaults();

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    root {$docroot};
    index index.html index.htm index.php;

    access_log {$home}/logs/nginx/access.log;
    error_log {$home}/logs/nginx/error.log;

    client_max_body_size 64M;

    {$httpBody}
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$domain} www.{$domain};
    root {$docroot};
    index index.html index.htm index.php;

    ssl_certificate {$certPath};
    ssl_certificate_key {$keyPath};
    {$tlsDefaults}

    access_log {$home}/logs/nginx/access.log;
    error_log {$home}/logs/nginx/error.log;

    client_max_body_size 64M;

    {$httpsBody}
}
NGINX;
    }

    protected function phpFpmLocations(string $username, string $home, string $docroot): string
    {
        $securityHeaders = $this->securityHeaders();
        $acmeLocation = $this->acmeLocation($docroot);

        return <<<NGINX
    {$securityHeaders}
    {$acmeLocation}

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/openpanel-php-user-{$username}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }
    location ~* \.(env|bak|sql|log|conf|ini|sh|py)\$ { deny all; }
    location ~ ^/(private|backups|logs|\.openpanel)/ { deny all; }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
NGINX;
    }

    protected function proxyLocations(string $docroot, int $backendPort): string
    {
        $securityHeaders = $this->securityHeaders();
        $acmeLocation = $this->acmeLocation($docroot);
        $staticBlock = '';
        $fallbackLocation = '';

        if ($backendPort === 6081) {
            $hostDomain = $this->domainFromDocroot($docroot);
            $settings = $hostDomain ? (new VarnishDomainService())->settings($hostDomain) : ['static_asset_mode' => 'nginx_direct', 'static_ttl' => 86400];
            if (($settings['static_asset_mode'] ?? 'nginx_direct') === 'nginx_direct') {
                $staticBlock = $this->staticDirectBlock((int) ($settings['static_ttl'] ?? 86400));
                $fallbackLocation = "\n    location @openpanel_backend {\n" . $this->proxyPassBlock($backendPort) . "\n    }\n";
            }
        }

        $mainProxy = $this->proxyPassBlock($backendPort);

        return <<<NGINX
    {$securityHeaders}
    {$acmeLocation}

    location ~ /\.(?!well-known).* { deny all; }
    location ~* (^|/)(wp-config\.php|\.env|composer\.(json|lock)|package(-lock)?\.json)\$ { deny all; }
    location ~* \.(env|bak|sql|log|conf|ini|sh|py)\$ { deny all; }
    location ~ ^/(private|backups|logs|\.openpanel)/ { deny all; }

    {$staticBlock}

    location / {
{$mainProxy}
    }

    {$fallbackLocation}
NGINX;
    }

    protected function domainFromDocroot(string $docroot): ?string
    {
        if (!preg_match('#^/home/([^/]+)/public_html$#', $docroot, $match)) {
            return null;
        }

        $account = DB::connection('mysql')->table('accounts')->where('username', $match[1])->first();
        return $account->domain ?? null;
    }

    protected function staticDirectBlock(int $staticTtl): string
    {
        return <<<NGINX
    location ~* \.(jpg|jpeg|png|gif|webp|avif|svg|ico|css|js|mjs|woff|woff2|ttf|eot|mp4|webm|mp3|wav|pdf|txt|xml)\$ {
        try_files \$uri @openpanel_backend;
        access_log off;
        expires {$staticTtl}s;
        add_header Cache-Control "public, max-age={$staticTtl}" always;
    }
NGINX;
    }

    protected function proxyPassBlock(int $backendPort): string
    {
        return <<<NGINX
        proxy_pass http://127.0.0.1:{$backendPort};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_pass_header X-Cache;
        proxy_pass_header Via;
        proxy_pass_header Age;
        proxy_pass_header X-Varnish;
NGINX;
    }

    protected function acmeLocation(string $docroot): string
    {
        return <<<NGINX
    location ^~ /.well-known/acme-challenge/ {
        root {$docroot};
        default_type "text/plain";
        try_files \$uri =404;
    }
NGINX;
    }

    protected function securityHeaders(): string
    {
        return <<<NGINX
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
NGINX;
    }

    protected function tlsDefaults(): string
    {
        return <<<NGINX
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
NGINX;
    }

    protected function safeInstallNginxVhost(string $username, string $vhost): array
    {
        $dir = '/etc/nginx/conf.d/users';
        $path = "{$dir}/{$username}.conf";
        $backup = "{$path}.bak." . date('YmdHis');

        Process::run('sudo mkdir -p ' . escapeshellarg($dir));
        $hadExisting = $this->pathExists($path);
        if ($hadExisting) {
            Process::run('sudo cp ' . escapeshellarg($path) . ' ' . escapeshellarg($backup));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ssl-nginx-');
        file_put_contents($tmp, $vhost);
        Process::run('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path));
        @unlink($tmp);

        $test = Process::run('sudo nginx -t 2>&1');
        if ($test->failed()) {
            $this->rollbackVhost($path, $backup, $hadExisting);

            return [
                'success' => false,
                'vhost_updated' => false,
                'nginx_reload_status' => 'skipped',
                'error' => $this->safeOutput($test->output() ?: $test->errorOutput()),
            ];
        }

        $reload = Process::run('sudo systemctl reload nginx 2>&1');
        if ($reload->failed()) {
            $this->rollbackVhost($path, $backup, $hadExisting);

            return [
                'success' => false,
                'vhost_updated' => false,
                'nginx_reload_status' => 'failed_rolled_back',
                'error' => $this->safeOutput($reload->output() ?: $reload->errorOutput()),
            ];
        }

        return [
            'success' => true,
            'vhost_updated' => true,
            'nginx_reload_status' => 'reloaded',
            'backup_path' => $hadExisting ? $backup : null,
        ];
    }

    protected function rollbackVhost(string $path, string $backup, bool $hadExisting): void
    {
        if ($hadExisting && $this->pathExists($backup)) {
            Process::run('sudo cp ' . escapeshellarg($backup) . ' ' . escapeshellarg($path));
        } else {
            Process::run('sudo rm -f ' . escapeshellarg($path));
        }

        $test = Process::run('sudo nginx -t 2>&1');
        if ($test->successful()) {
            Process::run('sudo systemctl reload nginx 2>&1');
        }
    }

    protected function ensureRenewalHook(): array
    {
        $script = <<<'BASH'
#!/bin/bash
set -u
LOG="/var/log/openpanel/letsencrypt-renewal.log"
mkdir -p /var/log/openpanel
if nginx -t >>"$LOG" 2>&1; then
    systemctl reload nginx >>"$LOG" 2>&1
    echo "$(date -Is) certbot deploy hook reloaded nginx" >>"$LOG"
else
    echo "$(date -Is) certbot deploy hook skipped reload: nginx config invalid" >>"$LOG"
    exit 1
fi
BASH;

        Process::run('sudo mkdir -p /etc/letsencrypt/renewal-hooks/deploy /var/log/openpanel');
        $tmp = tempnam(sys_get_temp_dir(), 'le-hook-');
        file_put_contents($tmp, $script);
        Process::run('sudo cp ' . escapeshellarg($tmp) . ' /etc/letsencrypt/renewal-hooks/deploy/openpanel-domain-ssl.sh');
        Process::run('sudo chmod 755 /etc/letsencrypt/renewal-hooks/deploy/openpanel-domain-ssl.sh');
        @unlink($tmp);

        $hasTimer = Process::run("systemctl list-timers --all 2>/dev/null | grep -Eq 'certbot(-renew)?\\.timer'")->successful();
        if (!$hasTimer) {
            $cron = "17 3 * * * root certbot renew --quiet >> /var/log/openpanel/letsencrypt-renewal.log 2>&1\n";
            $tmpCron = tempnam(sys_get_temp_dir(), 'le-cron-');
            file_put_contents($tmpCron, $cron);
            Process::run('sudo cp ' . escapeshellarg($tmpCron) . ' /etc/cron.d/openpanel-certbot-renew');
            Process::run('sudo chmod 644 /etc/cron.d/openpanel-certbot-renew');
            @unlink($tmpCron);
        }

        return [
            'auto_renew' => true,
            'timer_present' => $hasTimer,
            'cron_present' => !$hasTimer,
        ];
    }

    protected function recordSuccess(string $domain, array $paths, array $install, bool $forceHttps): void
    {
        $cert = @openssl_x509_parse($this->readTextFile($paths['cert']));
        $expiresAt = $cert && isset($cert['validTo_time_t']) ? Carbon::createFromTimestamp($cert['validTo_time_t']) : null;
        $issuedAt = $cert && isset($cert['validFrom_time_t']) ? Carbon::createFromTimestamp($cert['validFrom_time_t']) : now();

        $payload = $this->certificatePayload([
            'domain' => $domain,
            'type' => 'letsencrypt',
            'status' => 'active',
            'issuer' => $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? null),
            'serial_number' => $cert['serialNumberHex'] ?? null,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'auto_renew' => true,
            'force_https' => $forceHttps,
            'cert_path' => $paths['cert'],
            'key_path' => $paths['key'],
            'vhost_installed' => $install['success'],
            'last_renewal_status' => 'success',
            'last_error' => null,
        ]);

        $record = SslCertificate::withTrashed()
            ->where('domain', $domain)
            ->where('type', 'letsencrypt')
            ->latest()
            ->first();

        if ($record) {
            if ($record->trashed()) {
                $record->restore();
            }
            $record->fill($payload)->save();
        } else {
            SslCertificate::create($payload);
        }

        Domain::where('domain', $domain)->update([
            'ssl_enabled' => true,
            'ssl_certificate' => $paths['cert'],
            'ssl_key' => $paths['key'],
            'ssl_provider' => 'letsencrypt',
            'ssl_expires_at' => $expiresAt,
            'auto_ssl' => true,
            'force_https' => $forceHttps,
        ]);
    }

    protected function recordFailure(string $domain, string $status, string $error): void
    {
        try {
            $payload = $this->certificatePayload([
                'domain' => $domain,
                'type' => 'letsencrypt',
                'status' => 'pending',
                'auto_renew' => true,
                'last_renewal_status' => $status,
                'last_error' => $this->safeOutput($error),
            ]);

            $record = SslCertificate::withTrashed()
                ->where('domain', $domain)
                ->where('type', 'letsencrypt')
                ->latest()
                ->first();

            if ($record) {
                if ($record->trashed()) {
                    $record->restore();
                }
                $record->fill($payload)->save();
            } else {
                SslCertificate::create($payload);
            }
        } catch (\Throwable $e) {
            Log::warning('OpenPanel SSL failure tracking failed', ['domain' => $domain, 'error' => $e->getMessage()]);
        }
    }

    protected function certificatePayload(array $payload): array
    {
        if (!Schema::hasTable('ssl_certificates')) {
            return $payload;
        }

        return array_filter(
            $payload,
            fn(string $column) => Schema::hasColumn('ssl_certificates', $column),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function isVhostInstalled(string $domain, string $certPath, string $keyPath): bool
    {
        try {
            $account = $this->findAccount($domain);
        } catch (\Throwable) {
            return false;
        }

        $vhostPath = "/etc/nginx/conf.d/users/{$account['username']}.conf";
        if (!$this->pathExists($vhostPath)) {
            return false;
        }

        $content = $this->readTextFile($vhostPath);

        return str_contains($content, 'listen 443 ssl')
            && str_contains($content, "ssl_certificate {$certPath};")
            && str_contains($content, "ssl_certificate_key {$keyPath};");
    }

    protected function isForceHttpsInstalled(string $domain): bool
    {
        try {
            $account = $this->findAccount($domain);
        } catch (\Throwable) {
            return false;
        }

        $vhostPath = "/etc/nginx/conf.d/users/{$account['username']}.conf";
        if (!$this->pathExists($vhostPath)) {
            return false;
        }

        return str_contains($this->readTextFile($vhostPath), 'return 301 https://$host$request_uri;');
    }

    protected function storedForceHttps(string $domain): bool
    {
        $record = SslCertificate::where('domain', $domain)->where('type', 'letsencrypt')->latest()->first();
        if ($record && Schema::hasColumn('ssl_certificates', 'force_https')) {
            return (bool) $record->force_https;
        }

        $domainRow = Domain::where('domain', $domain)->first();
        if ($domainRow && Schema::hasColumn('domains', 'force_https')) {
            return (bool) $domainRow->force_https;
        }

        return false;
    }

    protected function purgeVarnish(string $domain): void
    {
        if (!in_array(WebStackService::getActiveStack(), ['nginx_varnish_apache', 'nginx_apache'], true)) {
            return;
        }

        Process::run('varnishadm ' . escapeshellarg('ban req.http.host == "' . $domain . '"') . ' 2>&1');
        Process::run('varnishadm ' . escapeshellarg('ban req.http.host == "www.' . $domain . '"') . ' 2>&1');
    }

    protected function syncWordPressHttps(string $domain, bool $forceHttps): bool
    {
        $site = WordPressSite::where('domain', $domain)->whereNull('deleted_at')->first();
        if (!$site || !is_dir($site->install_path)) {
            return false;
        }

        try {
            $account = $this->findAccount($domain);
            $scheme = $forceHttps ? 'https' : 'http';
            $url = "{$scheme}://{$domain}";
            $wp = new WordPressService();

            $home = $wp->runWpCli($site->install_path, 'option update home ' . escapeshellarg($url), $account['username'], 60);
            $siteUrl = $wp->runWpCli($site->install_path, 'option update siteurl ' . escapeshellarg($url), $account['username'], 60);
            $admin = $wp->runWpCli(
                $site->install_path,
                'config set FORCE_SSL_ADMIN ' . ($forceHttps ? 'true' : 'false') . ' --raw --type=constant',
                $account['username'],
                60
            );

            if (($home['success'] ?? false) || ($siteUrl['success'] ?? false) || ($admin['success'] ?? false)) {
                $site->update([
                    'site_url' => $url,
                    'ssl_enabled' => $forceHttps || $site->ssl_enabled,
                ]);
                return true;
            }
        } catch (\Throwable $e) {
            Log::info('OpenPanel SSL WordPress HTTPS sync skipped', ['domain' => $domain, 'error' => $e->getMessage()]);
        }

        return false;
    }

    protected function safeOutput(string $output): string
    {
        $output = preg_replace('/-----BEGIN [^-]+-----.*?-----END [^-]+-----/s', '[redacted-pem]', $output);
        $output = preg_replace('/Authorization:\s*\S+/i', 'Authorization: [redacted]', $output);
        $output = trim(preg_replace('/\s+/', ' ', (string) $output));

        return mb_substr($output ?: 'unknown error', 0, 500);
    }

    protected function pathExists(string $path): bool
    {
        return file_exists($path)
            || Process::run('sudo test -f ' . escapeshellarg($path))->successful();
    }

    protected function readTextFile(string $path): string
    {
        if (is_readable($path)) {
            return (string) file_get_contents($path);
        }

        $result = Process::run('sudo cat ' . escapeshellarg($path) . ' 2>/dev/null');

        return $result->successful() ? $result->output() : '';
    }
}
