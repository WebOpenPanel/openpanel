<?php

namespace App\Services;

use App\Models\Package;
use App\Services\ShellService;
use App\Services\ResourceControlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class AccountService
{
    protected string $homeBase = '/home';
    protected string $dnsZoneDir = '/var/named';
    protected string $nginxVhostDir = '/etc/nginx/conf.d/users';
    protected string $phpFpmPoolDir = '/etc/php-fpm.d/users';

    // =========================================================================
    // LIST / GET
    // =========================================================================

    public function listUsers(): array
    {
        $users = [];
        $result = Process::run("getent passwd");
        if ($result->successful()) {
            foreach (explode("\n", trim($result->output())) as $line) {
                $parts = explode(':', $line);
                if (count($parts) >= 6 && str_starts_with($parts[5], $this->homeBase . '/')) {
                    $users[] = [
                        'username' => $parts[0],
                        'uid' => (int) $parts[2],
                        'gid' => (int) $parts[3],
                        'home' => $parts[5],
                        'shell' => $parts[6],
                    ];
                }
            }
        }
        return $users;
    }

    public function getUser(string $username): ?array
    {
        $result = Process::run("getent passwd " . escapeshellarg($username));
        if ($result->failed()) return null;

        $parts = explode(':', $result->output());
        if (count($parts) < 6) return null;

        $user = [
            'username' => $parts[0],
            'uid' => (int) $parts[2],
            'gid' => (int) $parts[3],
            'home' => $parts[5],
            'shell' => $parts[6],
        ];

        // Check if account exists in database
        $dbUser = DB::connection('mysql')->table('accounts')->where('username', $username)->first();
        if ($dbUser) {
            $user['domain'] = $dbUser->domain;
            $user['email'] = $dbUser->email;
            $user['package'] = $dbUser->package;
            $user['disk_limit'] = $dbUser->disk_limit;
            $user['bandwidth_limit'] = $dbUser->bandwidth_limit;
            $user['suspended'] = ($dbUser->status === 'suspended');
            $user['created_at'] = $dbUser->created_at;
        }

        // Get disk usage
        $du = Process::run("du -sm " . escapeshellarg($user['home']) . " 2>/dev/null | awk '{print $1}'");
        $user['disk_usage_mb'] = $du->successful() ? (int) trim($du->output()) : 0;

        return $user;
    }

    public function getUserStats(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) return [];

        $stats = [
            'disk_usage_mb' => $user['disk_usage_mb'],
            'disk_limit_mb' => $user['disk_limit'] ?? 0,
            'bandwidth_limit_mb' => $user['bandwidth_limit'] ?? 0,
        ];

        // Process count
        $ps = Process::run("ps -u " . escapeshellarg($username) . " --no-headers 2>/dev/null | wc -l");
        $stats['process_count'] = $ps->successful() ? (int) trim($ps->output()) : 0;

        // Database count
        $dbCount = DB::connection('mysql')->table('mysql.db')
            ->where('User', $username)
            ->count();
        $stats['database_count'] = $dbCount;

        return $stats;
    }

    public function getAccountLimits(string $username): array
    {
        $dbUser = DB::connection('mysql')->table('accounts')->where('username', $username)->first();
        if (!$dbUser) return [];

        $package = Package::where('name', $dbUser->package)->first();
        if (!$package) return [];

        return $package->toArray();
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create(array $data): array
    {
        $this->validateCreateData($data);

        $username = $data['username'];
        $domain = $data['domain'];
        $password = $data['password'];
        $ip = $data['ip'] ?? $this->getDefaultIp();
        $email = $data['email'] ?? "admin@{$domain}";
        $package = $data['package'] ?? 'default';
        $disk = $data['disk_limit'] ?? 1024;
        $bandwidth = $data['bandwidth_limit'] ?? 10240;

        $home = "{$this->homeBase}/{$username}";

        // Check if user already exists
        if ($this->getUser($username)) {
            throw new \RuntimeException("Account '{$username}' already exists.");
        }

        // Create system user
        $this->createSystemUser($username, $password, $home);

        // Create home directory structure
        $this->createHomeStructure($username, $domain);

        // Create DNS zone
        $this->createDnsZone($username, $domain, $ip);

        // Generate web server vhost and reload
        $activeStack = WebStackService::getActiveStack();
        $this->stackService->generateVhostForDomain($activeStack, $username, $domain, $home);
        // Re-apply group membership now that stack is fully configured
        $this->addWebServerToGroup($username);
        $this->createPhpFpmPool($username);
        $this->createEmailDomain($username, $domain);
        $this->setupFtpUser($username, $password);
        $this->recordInDatabase($username, $domain, $ip, $email, $package, $disk, $bandwidth);

        // Apply resource limits from package (cgroups, nproc, disk quotas)
        // Wrapped in try-catch — resource limits are best-effort, don't block account creation
        try { ResourceControlService::applyForUser($username, $package); } catch (\Throwable $e) {}

        $this->reloadServices();

        return [
            'success' => true,
            'username' => $username,
            'domain' => $domain,
            'ip' => $ip,
            'home' => $home,
            'message' => "Account '{$username}' created successfully.",
        ];
    }

    // =========================================================================
    // TERMINATE
    // =========================================================================

    public function terminate(string $username, bool $keepDns = false): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("Account '{$username}' not found.");
        }

        $domain = $user['domain'] ?? '';

        $this->removePhpFpmPool($username);
        $this->stackService->removeVhostForDomain(
            WebStackService::getActiveStack(),
            $username,
            $domain
        );
        if (!$keepDns && $domain) {
            $this->removeDnsZone($username, $domain);
        }
        $this->removeEmailDomain($domain);
        ResourceControlService::removeFromUser($username);
        $this->removeSystemUser($username);

        DB::connection('mysql')->table('accounts')->where('username', $username)->delete();

        $this->reloadServices();

        return [
            'success' => true,
            'username' => $username,
            'message' => "Account '{$username}' terminated successfully.",
        ];
    }

    // =========================================================================
    // SUSPEND / UNSUSPEND
    // =========================================================================

    public function suspend(string $username, string $reason = ''): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("Account '{$username}' not found.");
        }

        $domain = $user['domain'] ?? '';

        // Lock the OS account
        Process::run("sudo passwd -l " . escapeshellarg($username) . " 2>/dev/null");
        Process::run("sudo chage -E 0 " . escapeshellarg($username) . " 2>/dev/null");

        // Write suspended vhosts (nginx 403 + Varnish ban + Apache 403)
        $stack = WebStackService::getActiveStack();
        $suspendResult = $this->stackService->suspendDomain($stack, $username, $domain);

        DB::connection('mysql')->table('accounts')
            ->where('username', $username)
            ->update([
                'status' => 'suspended',
                'updated_at' => now(),
            ]);

        $this->reloadServices();

        return [
            'success' => true,
            'username' => $username,
            'message' => "Account '{$username}' suspended.",
            'actions' => $suspendResult['actions'] ?? [],
        ];
    }

    public function unsuspend(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("Account '{$username}' not found.");
        }

        $domain = $user['domain'] ?? '';

        // Unlock the OS account
        Process::run("sudo passwd -u " . escapeshellarg($username) . " 2>/dev/null");
        Process::run("sudo chage -E -1 " . escapeshellarg($username) . " 2>/dev/null");

        // Remove suspended vhosts and restore normal vhosts
        $stack = WebStackService::getActiveStack();
        $unsuspendResult = $this->stackService->unsuspendDomain($stack, $username, $domain);

        DB::connection('mysql')->table('accounts')
            ->where('username', $username)
            ->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);

        $this->reloadServices();

        return [
            'success' => true,
            'username' => $username,
            'message' => "Account '{$username}' unsuspended.",
            'actions' => $unsuspendResult['actions'] ?? [],
        ];
    }

    // =========================================================================
    // CHANGE PACKAGE
    // =========================================================================

    public function changePackage(string $username, string $newPackage): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("Account '{$username}' not found.");
        }

        $pkg = Package::where('name', $newPackage)->first();
        if (!$pkg) {
            throw new \RuntimeException("Package '{$newPackage}' not found.");
        }

        DB::connection('mysql')->table('accounts')
            ->where('username', $username)
            ->update([
                'package' => $newPackage,
                'disk_limit' => $pkg->disk_space_mb,
                'bandwidth_limit' => $pkg->bandwidth_mb,
                'updated_at' => now(),
            ]);

        // Re-apply resource limits for the new package
        try { ResourceControlService::applyForUser($username, $newPackage); } catch (\Throwable $e) {}

        return [
            'success' => true,
            'username' => $username,
            'package' => $newPackage,
            'message' => "Package changed to '{$newPackage}'.",
        ];
    }

    // =========================================================================
    // CHANGE PASSWORD
    // =========================================================================

    public function changePassword(string $username, string $newPassword): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("Account '{$username}' not found.");
        }

        $result = Process::run("echo " . escapeshellarg($newPassword) . " | sudo /usr/bin/passwd --stdin " . escapeshellarg($username) . " 2>&1");
        if ($result->failed()) {
            throw new \RuntimeException("Failed to change password: " . $result->errorOutput());
        }

        return [
            'success' => true,
            'username' => $username,
            'message' => "Password changed successfully.",
        ];
    }

    // =========================================================================
    // REPAIR ISOLATION
    // =========================================================================

    public function repairUserIsolation(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("Account '{$username}' not found.");
        }

        $home = $user['home'];
        $result = ['username' => $username, 'actions' => [], 'success' => true];

        if (!is_dir($home)) {
            $result['success'] = false;
            $result['actions'][] = 'home_dir_missing';
            return $result;
        }

        // 1. Fix home directory ownership
        Process::run("sudo chown {$username}:{$username} " . escapeshellarg($home));
        $result['actions'][] = 'home_ownership_fixed';

        // 2. Fix home directory permissions (711 = traverse only)
        Process::run("sudo chmod 711 " . escapeshellarg($home));
        $result['actions'][] = 'home_perms_711';

        // 3. Ensure required directories exist with correct permissions
        $requiredDirs = [
            'public_html' => '750',
            'private' => '700',
            'backups' => '700',
            '.openpanel' => '700',
            'tmp' => '700',
            'logs' => '700',
            'logs/nginx' => '700',
            'logs/apache' => '700',
            '.ssh' => '700',
            'mail' => '700',
        ];
        foreach ($requiredDirs as $dir => $perm) {
            $fullPath = "{$home}/{$dir}";
            if (!is_dir($fullPath)) {
                Process::run("sudo mkdir -p " . escapeshellarg($fullPath));
                $result['actions'][] = "created_{$dir}";
            }
            Process::run("sudo chmod {$perm} " . escapeshellarg($fullPath));
        }

        // 4. Fix ownership of all user files recursively
        Process::run("sudo chown -R {$username}:{$username} " . escapeshellarg($home));
        $result['actions'][] = 'ownership_recursion_fixed';

        // 5. Remove world-writable files in home
        $ww = Process::run("sudo find " . escapeshellarg($home) . " -type f -perm -0002 -print 2>/dev/null");
        if ($ww->successful() && trim($ww->output())) {
            foreach (explode("\n", trim($ww->output())) as $f) {
                if ($f) Process::run("sudo chmod o-w " . escapeshellarg($f));
            }
            $result['actions'][] = 'world_writable_fixed';
        }

        // 6. Remove dangerous symlinks pointing outside home
        $symlinks = Process::run("sudo find " . escapeshellarg($home) . " -type l 2>/dev/null");
        if ($symlinks->successful()) {
            foreach (explode("\n", trim($symlinks->output())) as $link) {
                if (!$link) continue;
                $target = Process::run("sudo readlink -f " . escapeshellarg($link));
                if ($target->successful() && !str_starts_with(trim($target->output()), $home)) {
                    Process::run("sudo rm -f " . escapeshellarg($link));
                    $result['actions'][] = "removed_symlink_{$link}";
                }
            }
        }
        $result['actions'][] = 'dangerous_symlinks_removed';

        // 7. Ensure web servers are in user's group (fixes 403 on public_html 750)
        $this->addWebServerToGroup($username);
        $result['actions'][] = 'web_server_group_fixed';

        // 8. Fix PHP-FPM pool if missing or misconfigured
        $poolFile = "{$this->phpFpmPoolDir}/{$username}.conf";
        if (!file_exists($poolFile)) {
            $this->createPhpFpmPool($username);
            $result['actions'][] = 'php_fpm_pool_created';
        } else {
            // Validate pool config
            $check = Process::run("sudo php-fpm -t 2>&1");
            if ($check->failed()) {
                $this->createPhpFpmPool($username);
                $result['actions'][] = 'php_fpm_pool_recreated';
            }
        }

        // 9. Reload PHP-FPM if pool was modified
        if (in_array('php_fpm_pool_created', $result['actions']) ||
            in_array('php_fpm_pool_recreated', $result['actions'])) {
            Process::run("sudo systemctl reload php-fpm 2>/dev/null");
        }

        // 10. Fix .htaccess if missing
        $htaccessPath = "{$home}/public_html/.htaccess";
        if (!file_exists($htaccessPath) && is_dir("{$home}/public_html")) {
            $domain = $user['domain'] ?? $username;
            $this->createHomeStructure($username, $domain);
            $result['actions'][] = 'htaccess_restored';
        }

        return $result;
    }

    // =========================================================================
    // SYSTEM USER OPERATIONS
    // =========================================================================

    protected function createSystemUser(string $username, string $password, string $home): void
    {
        // Determine shell: allow override from package data
        $shell = '/sbin/nologin';

        // Create system user
        $result = Process::run("sudo /usr/sbin/useradd -m -d " . escapeshellarg($home) . " -s " . escapeshellarg($shell) . " " . escapeshellarg($username) . " 2>&1");
        if ($result->failed()) {
            throw new \RuntimeException("Failed to create user: " . $result->output());
        }

        // Set password
        $result = Process::run("echo " . escapeshellarg($password) . " | sudo /usr/bin/passwd --stdin " . escapeshellarg($username) . " 2>&1");
        if ($result->failed()) {
            Process::run("sudo /usr/sbin/userdel -r " . escapeshellarg($username));
            throw new \RuntimeException("Failed to set password: " . ($result->errorOutput() ?: $result->output()));
        }

        Process::run("sudo /usr/bin/chown {$username}:{$username} " . escapeshellarg($home));
        // Set home to 711 immediately so runAsUser can cd into it later
        Process::run("sudo /usr/bin/chmod 711 " . escapeshellarg($home));
    }

    /**
     * Add web server users (nginx, apache) to the hosting user's group.
     * This is required because public_html has 750 perms (group-readable).
     */
    protected function addWebServerToGroup(string $username): void
    {
        // nginx is always present on all stacks
        Process::run("sudo /usr/sbin/usermod -aG {$username} nginx 2>/dev/null || true");

        // apache only present on nginx_varnish_apache stack — check before adding
        $apacheCheck = Process::run("id apache 2>/dev/null");
        if ($apacheCheck->successful()) {
            Process::run("sudo /usr/sbin/usermod -aG {$username} apache");
        }
    }

    protected function removeSystemUser(string $username): void
    {
        Process::run("sudo userdel -r " . escapeshellarg($username) . " 2>/dev/null");
    }

    protected function createHomeStructure(string $username, string $domain): void
    {
        $home = "{$this->homeBase}/{$username}";

        // Create directories using sudo (API runs as nginx, not root)
        $dirs = [
            'public_html', 'public_html/cgi-bin', '.ssh',
            'logs', 'logs/nginx', 'logs/apache',
            'tmp', 'mail', 'backups', 'private', '.openpanel',
        ];
        Process::run("sudo mkdir -p " . implode(' ', array_map(fn($d) => escapeshellarg("{$home}/{$d}"), $dirs)));

        // Write index.html via temp file
        $index = <<<HTML
<!DOCTYPE html>
<html>
<head><title>{$domain}</title></head>
<body>
<h1>Welcome to {$domain}</h1>
<p>This site is hosted on OpenPanel.</p>
</body>
</html>
HTML;

        $tmpIndex = tempnam(sys_get_temp_dir(), 'idx');
        file_put_contents($tmpIndex, $index);
        Process::run("sudo cp " . escapeshellarg($tmpIndex) . " " . escapeshellarg("{$home}/public_html/index.html"));
        @unlink($tmpIndex);

        // Write .htaccess ONLY if missing — preserves CMS rules (WordPress, etc.)
        $htaccessPath = "{$home}/public_html/.htaccess";
        if (!file_exists($htaccessPath)) {
            $htaccess = <<<'HTACCESS'
# Disable directory listing
Options -Indexes

# Block access to hidden files (except .htaccess itself)
<FilesMatch "^\.">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

# Block access to sensitive files
<FilesMatch "\.(env|bak|sql|log|conf|ini|sh|py|php\.)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

# Prevent PHP execution in uploads directory
<IfModule mod_php.c>
    <DirectoryMatch ".*/uploads/">
        php_flag engine off
    </DirectoryMatch>
</IfModule>
HTACCESS;

            $tmpHt = tempnam(sys_get_temp_dir(), 'ht');
            file_put_contents($tmpHt, $htaccess);
            Process::run("sudo cp " . escapeshellarg($tmpHt) . " " . escapeshellarg("{$home}/public_html/.htaccess"));
            @unlink($tmpHt);
        }

        // Set ownership and permissions using sudo
        Process::run("sudo chown -R {$username}:{$username} " . escapeshellarg($home));

        $perms = [
            '.' => '711',
            'public_html' => '750',
            'public_html/cgi-bin' => '1777',
            '.ssh' => '700',
            'logs' => '700',
            'tmp' => '700',
            'mail' => '700',
            'backups' => '700',
            'private' => '700',
            '.openpanel' => '700',
        ];
        foreach ($perms as $path => $perm) {
            Process::run("sudo chmod {$perm} " . escapeshellarg("{$home}/{$path}"));
        }
    }

    // =========================================================================
    // DNS
    // =========================================================================

    protected function createDnsZone(string $username, string $domain, string $ip): void
    {
        $serial = date('Ymd') . '01';
        $zoneFile = "{$this->dnsZoneDir}/{$domain}.db";

        $zone = <<<BIND
\$TTL 14400
@   IN  SOA ns1.{$domain}. admin.{$domain}. (
        {$serial}  ; serial
        3600       ; refresh
        1800       ; retry
        1209600    ; expire
        86400 )    ; minimum

    IN  NS  ns1.{$domain}.
    IN  NS  ns2.{$domain}.
    IN  A   {$ip}

ns1 IN  A   {$ip}
ns2 IN  A   {$ip}
www IN  A   {$ip}
@   IN  A   {$ip}
BIND;

        $tmp = tempnam(sys_get_temp_dir(), 'dns');
        file_put_contents($tmp, $zone);
        Process::run("sudo cp " . escapeshellarg($tmp) . " " . escapeshellarg($zoneFile));
        Process::run("sudo chown named:named " . escapeshellarg($zoneFile));
        @unlink($tmp);
    }

    protected function removeDnsZone(string $username, string $domain): void
    {
        $zoneFile = "{$this->dnsZoneDir}/{$domain}.db";
        Process::run("sudo rm -f " . escapeshellarg($zoneFile));
    }

    // =========================================================================
    // NGINX VHOST
    // =========================================================================

    protected function removeNginxVhost(string $username): void
    {
        Process::run("sudo rm -f " . escapeshellarg("{$this->nginxVhostDir}/{$username}.conf"));
    }

    // =========================================================================
    // PHP-FPM POOL
    // =========================================================================

    protected function createPhpFpmPool(string $username): void
    {
        $home = "{$this->homeBase}/{$username}";
        $pool = <<<CONF
[{$username}]
user = {$username}
group = {$username}
listen = /run/php-fpm-{$username}.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 300s
pm.max_requests = 500

php_admin_value[error_log] = {$home}/logs/php-error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 128M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 120
php_admin_value[max_file_uploads] = 20

; Security isolation
php_admin_value[open_basedir] = {$home}:/tmp:/usr/share/php
php_admin_value[disable_functions] = passthru,system,dl,putenv,pcntl_exec,proc_nice,proc_terminate,proc_close,show_source
php_admin_flag[expose_php] = off
php_admin_value[session.save_path] = {$home}/tmp
php_admin_value[upload_tmp_dir] = {$home}/tmp
php_admin_value[sys_temp_dir] = {$home}/tmp
php_admin_flag[display_errors] = off
CONF;

        Process::run("sudo mkdir -p {$this->phpFpmPoolDir}");
        $tmp = tempnam(sys_get_temp_dir(), 'fpm');
        file_put_contents($tmp, $pool);
        Process::run("sudo cp " . escapeshellarg($tmp) . " {$this->phpFpmPoolDir}/{$username}.conf");
        @unlink($tmp);
    }

    protected function removePhpFpmPool(string $username): void
    {
        Process::run("sudo rm -f " . escapeshellarg("{$this->phpFpmPoolDir}/{$username}.conf"));
    }

    // =========================================================================
    // EMAIL
    // =========================================================================

    protected function createEmailDomain(string $username, string $domain): void
    {
        // Create mail directory
        $home = "{$this->homeBase}/{$username}";
        Process::run("sudo mkdir -p {$home}/mail/{$domain}");

        // Add domain to postfix virtual domains
        $vhostFile = '/etc/postfix/vhost';
        if (file_exists($vhostFile)) {
            $existing = Process::run("sudo grep -q " . escapeshellarg($domain) . " " . escapeshellarg($vhostFile) . " 2>/dev/null");
            if ($existing->failed()) {
                Process::run("sudo bash -c " . escapeshellarg("echo " . escapeshellarg($domain) . " >> " . escapeshellarg($vhostFile)));
            }
        } else {
            Process::run("sudo bash -c " . escapeshellarg("echo " . escapeshellarg($domain) . " > " . escapeshellarg($vhostFile)));
        }
    }

    protected function removeEmailDomain(string $domain): void
    {
        $vhostFile = '/etc/postfix/vhost';
        if (file_exists($vhostFile) && $domain) {
            Process::run("sudo sed -i '/" . preg_quote($domain, '/') . "/d' " . escapeshellarg($vhostFile));
        }
    }

    // =========================================================================
    // FTP
    // =========================================================================

    protected function setupFtpUser(string $username, string $password): void
    {
        $home = "{$this->homeBase}/{$username}";
        try {
            FtpService::addUser($username, $username, $password, $home, 'openpanel');
        } catch (\Throwable $e) {
            // FTP setup is non-critical — log but don't fail account creation
            \Log::warning("FTP user setup failed for {$username}: " . $e->getMessage());
        }
    }

    // =========================================================================
    // DATABASE RECORD
    // =========================================================================

    protected function recordInDatabase(string $username, string $domain, string $ip, string $email, string $package, int $disk, int $bandwidth): void
    {
        DB::connection('mysql')->table('accounts')->insert([
            'username' => $username,
            'domain' => $domain,
            'ip_address' => $ip,
            'email' => $email,
            'package' => $package,
            'disk_limit' => $disk,
            'bandwidth_limit' => $bandwidth,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // SERVICES
    // =========================================================================

    public function reloadServices(): void
    {
        $stack = WebStackService::getActiveStack();
        $services = (new WebStackService())->getStackServices($stack);

        foreach ($services as $service => $config) {
            if ($config['reloadable'] ?? false) {
                Process::run("sudo systemctl reload {$service} 2>/dev/null");
            }
        }

        // Always reload PHP-FPM for pool changes
        Process::run("sudo systemctl reload php-fpm 2>/dev/null");
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function validateCreateData(array $data): void
    {
        if (empty($data['username'])) {
            throw new \RuntimeException("Username is required.");
        }
        if (empty($data['domain'])) {
            throw new \RuntimeException("Domain is required.");
        }
        if (empty($data['password'])) {
            throw new \RuntimeException("Password is required.");
        }

        $username = $data['username'];
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $username)) {
            throw new \RuntimeException("Username must start with a letter and contain only lowercase letters, numbers, and underscores.");
        }
        if (strlen($username) < 2 || strlen($username) > 32) {
            throw new \RuntimeException("Username must be between 2 and 32 characters.");
        }
    }

    protected function getDefaultIp(): string
    {
        // Try database settings table first
        try {
            $setting = DB::connection('mysql')->table('settings')->where('key', 'server_ip')->first();
            if ($setting) return $setting->value;
        } catch (\Throwable $e) {}

        $result = Process::run("hostname -I | awk '{print $1}'");
        return $result->successful() ? trim($result->output()) : '127.0.0.1';
    }

    // =========================================================================
    // API COMPATIBILITY (delegate to WebStackService)
    // =========================================================================

    public function getSuspendedVhosts(): array
    {
        return $this->stackService->getSuspendedVhosts();
    }

    public function getNginxVhostContent(string $username): ?string
    {
        $path = "/etc/nginx/conf.d/users/{$username}.conf";
        return file_exists($path) ? file_get_contents($path) : null;
    }

    public function getApacheVhostContent(string $username): ?string
    {
        $path = "/etc/httpd/conf.d/users/{$username}.conf";
        return file_exists($path) ? file_get_contents($path) : null;
    }

    // =========================================================================
    // STACK SERVICE PROXY
    // =========================================================================

    protected WebStackService $stackService;

    public function __construct()
    {
        $this->stackService = new WebStackService();
    }

    public function __call(string $name, array $arguments)
    {
        return $this->stackService->$name(...$arguments);
    }
}
