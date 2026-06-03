<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\ShellService;

class AccountService
{
    protected string $homeBase = '/home';
    protected string $nginxVhostDir = '/etc/nginx/conf.d/users';
    protected string $phpFpmPoolDir = '/etc/php-fpm.d/users';
    protected string $dnsZoneDir = '/var/named';
    protected WebStackService $stackService;

    public function __construct(?WebStackService $stackService = null)
    {
        $this->stackService = $stackService ?? new WebStackService();
    }

    public function create(array $data): array
    {
        $this->validateInput($data);

        $username = strtolower($data['username']);
        $domain = strtolower($data['domain']);
        $password = $data['password'];
        $ip = $data['ip'] ?? $this->getServerIp();
        $email = $data['email'] ?? "admin@{$domain}";
        $package = $data['package'] ?? 'default';
        $disk = $data['disk_limit'] ?? 1000;
        $bandwidth = $data['bandwidth_limit'] ?? 1000;

        if ($this->userExists($username)) {
            throw new \RuntimeException("User '{$username}' already exists on this server.");
        }

        $this->createSystemUser($username, $password, $domain);
        $this->createHomeStructure($username, $domain);
        $this->createDnsZone($username, $domain, $ip);
        $activeStack = $this->stackService->getActiveStack();
        $home = "{$this->homeBase}/{$username}";
        $this->stackService->generateVhostForDomain($activeStack, $username, $domain, $home);
        // Re-apply group membership now that stack is fully configured
        $this->addWebServerToGroup($username);
        $this->createPhpFpmPool($username);
        $this->createEmailDomain($username, $domain);
        $this->setupFtpUser($username, $password);
        $this->recordInDatabase($username, $domain, $ip, $email, $package, $disk, $bandwidth);
        $this->reloadServices();

        return [
            'success' => true,
            'username' => $username,
            'domain' => $domain,
            'ip' => $ip,
            'home' => "{$this->homeBase}/{$username}",
            'message' => "Account '{$username}' created successfully.",
        ];
    }

    public function delete(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("User '{$username}' not found.");
        }

        $this->removeFtpUser($username);
        $this->removePhpFpmPool($username);
        $activeStack = $this->stackService->getActiveStack();
        $this->stackService->removeVhostForDomain($activeStack, $username, $user['domain']);
        $this->removeDnsZone($username, $user['domain']);
        $this->removeEmailDomain($user['domain']);
        $this->removeSystemUser($username);
        $this->removeFromDatabase($username);
        $this->reloadServices();

        return [
            'success' => true,
            'message' => "Account '{$username}' deleted.",
        ];
    }

    public function suspend(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("User '{$username}' not found.");
        }

        $domain = $user['domain'] ?? '';

        // 1. Lock OS account
        Process::run("passwd -l {$username}");
        Process::run("chage -E 0 {$username}");

        // 2. Update DB status
        $this->updateDatabaseField($username, 'status', 'suspended');

        // 3. Stack-aware suspension: 403 vhost + Varnish ban
        $stackResult = $this->stackService->suspendDomain($username, $domain);

        // 4. WordPress Varnish purge if applicable
        $wpPurgeResult = null;
        try {
            $site = DB::connection('mysql')->table('wordpress_sites')
                ->where('domain', $domain)->first();
            if ($site) {
                $this->stackService->purgeVarnishCache($domain);
                $wpPurgeResult = 'purged';
            }
        } catch (\Throwable $e) {
            $wpPurgeResult = 'skipped: ' . $e->getMessage();
        }

        return [
            'success' => true,
            'message' => "Account '{$username}' suspended.",
            'domain' => $domain,
            'stack_actions' => $stackResult['actions'] ?? [],
            'varnish_purge' => $wpPurgeResult,
        ];
    }

    public function unsuspend(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("User '{$username}' not found.");
        }

        $domain = $user['domain'] ?? '';

        // 1. Unlock OS account
        Process::run("passwd -u {$username}");
        Process::run("chage -E -1 {$username}");

        // 2. Update DB status
        $this->updateDatabaseField($username, 'status', 'active');

        // 3. Stack-aware unsuspend: restore vhost + Varnish purge
        $stackResult = $this->stackService->unsuspendDomain($username, $domain);

        return [
            'success' => true,
            'message' => "Account '{$username}' unsuspended.",
            'domain' => $domain,
            'stack_actions' => $stackResult['actions'] ?? [],
        ];
    }

    public function changePassword(string $username, string $newPassword): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pw');
        file_put_contents($tmpFile, "{$username}:{$newPassword}\n");
        $result = Process::run("sudo /usr/sbin/chpasswd < " . escapeshellarg($tmpFile));
        @unlink($tmpFile);
        if ($result->failed()) {
            throw new \RuntimeException("Failed to change password: " . ($result->errorOutput() ?: $result->output()));
        }
        return ['success' => true, 'message' => "Password changed for '{$username}'."];
    }

    public function listUsers(): array
    {
        $result = Process::run("awk -F: '(\$3 >= 1000 && \$3 < 65534) || \$3 == 0 {print \$1}' /etc/passwd");
        $users = array_filter(explode("\n", trim($result->output())));
        return array_values($users);
    }

    public function getUser(string $username): ?array
    {
        $row = DB::connection('mysql')->table('accounts')->where('username', $username)->first();
        return $row ? (array) $row : null;
    }

    public function getServerIp(): string
    {
        $result = Process::run("hostname -I | awk '{print $1}'");
        return trim($result->output()) ?: '127.0.0.1';
    }

    /**
     * Repair filesystem isolation for a user account.
     * Fixes ownership, permissions, missing dirs, and dangerous configurations.
     */
    public function repairUserIsolation(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("User '{$username}' not found.");
        }

        $home = "{$this->homeBase}/{$username}";
        $result = ['username' => $username, 'actions' => [], 'success' => true];

        if (!is_dir($home)) {
            $result['success'] = false;
            $result['actions'][] = 'home_dir_missing';
            return $result;
        }

        // 1. Fix home directory ownership
        Process::run("chown {$username}:{$username} {$home}");
        $result['actions'][] = 'home_ownership_fixed';

        // 2. Fix home directory permissions (711 = traverse only)
        Process::run("chmod 711 {$home}");
        $result['actions'][] = 'home_perms_711';

        // 3. Ensure required directories exist
        $requiredDirs = [
            'public_html', 'public_html/cgi-bin', 'public_html/uploads',
            '.ssh', 'logs', 'logs/nginx', 'logs/apache',
            'tmp', 'mail', 'backups', 'private', '.openpanel',
        ];
        foreach ($requiredDirs as $dir) {
            $fullPath = "{$home}/{$dir}";
            if (!is_dir($fullPath)) {
                Process::run("sudo -u {$username} mkdir -p " . escapeshellarg($fullPath));
                $result['actions'][] = "created_{$dir}";
            }
        }

        // 4. Fix directory permissions
        $perms = [
            'public_html' => '750',
            '.ssh' => '700',
            'logs' => '700',
            'tmp' => '700',
            'backups' => '700',
            'private' => '700',
            '.openpanel' => '700',
        ];
        foreach ($perms as $dir => $perm) {
            $fullPath = "{$home}/{$dir}";
            if (is_dir($fullPath)) {
                Process::run("chmod {$perm} " . escapeshellarg($fullPath));
            }
        }
        $result['actions'][] = 'directory_perms_fixed';

        // 5. Fix ownership of all user files
        Process::run("chown -R {$username}:{$username} {$home}");
        $result['actions'][] = 'ownership_recursion_fixed';

        // 6. Remove world-writable files in home (security risk)
        Process::run("find {$home} -type f -perm -0002 -exec chmod o-w {} + 2>/dev/null");
        Process::run("find {$home} -type d -perm -0002 -not -path '*/tmp' -exec chmod o-w {} + 2>/dev/null");
        $result['actions'][] = 'world_writable_removed';

        // 7. Remove symlinks pointing outside home (symlink attack defense)
        $symlinkResult = Process::run("find {$home} -type l ! -exec test -e {} \\; -delete 2>/dev/null");
        Process::run("find {$home} -type l -exec readlink -f {} \\; 2>/dev/null | while read target; do
            case \"\$target\" in
                {$home}*) ;; # OK — points inside home
                *) find {$home} -type l -lname \"*\$(basename \$target)*\" -delete 2>/dev/null ;;
            esac
done");
        $result['actions'][] = 'dangerous_symlinks_removed';

        // 8. Ensure web servers are in user's group (fixes 403 on public_html 750)
        $this->addWebServerToGroup($username);
        $result['actions'][] = 'web_server_group_fixed';

        // 9. Fix PHP-FPM pool if missing or misconfigured
        $poolPath = "{$this->phpFpmPoolDir}/{$username}.conf";
        if (!file_exists($poolPath)) {
            $this->createPhpFpmPool($username);
            $result['actions'][] = 'php_fpm_pool_created';
        } else {
            // Validate pool has security directives
            $poolContent = file_get_contents($poolPath);
            $needsUpdate = false;
            if (strpos($poolContent, 'disable_functions') === false) {
                $needsUpdate = true;
            }
            if (strpos($poolContent, 'open_basedir') === false) {
                $needsUpdate = true;
            }
            if ($needsUpdate) {
                $this->createPhpFpmPool($username);
                $result['actions'][] = 'php_fpm_pool_hardened';
            }
        }

        // 10. Reload PHP-FPM if pool was modified
        if (in_array('php_fpm_pool_created', $result['actions']) ||
            in_array('php_fpm_pool_hardened', $result['actions'])) {
            Process::run("systemctl reload php-fpm 2>/dev/null");
            $result['actions'][] = 'php_fpm_reloaded';
        }

        // 11. Fix .htaccess if missing
        $htaccessPath = "{$home}/public_html/.htaccess";
        if (!file_exists($htaccessPath)) {
            $domain = $user['domain'] ?? $username;
            // Re-create home structure elements
            $this->createHomeStructure($username, $domain);
            $result['actions'][] = 'htaccess_restored';
        }

        return $result;
    }

    protected function validateInput(array $data): void
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
        if (strlen($data['username']) < 2 || strlen($data['username']) > 32) {
            throw new \RuntimeException("Username must be 2-32 characters.");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $data['username'])) {
            throw new \RuntimeException("Username must start with a letter and contain only lowercase letters, numbers, underscores.");
        }
    }

    protected function userExists(string $username): bool
    {
        $result = Process::run("id {$username} 2>/dev/null");
        return $result->successful();
    }

    protected function createSystemUser(string $username, string $password, string $domain): void
    {
        $home = "{$this->homeBase}/{$username}";

        // Use nologin shell by default — shell access is admin-controlled via package
        $shell = '/sbin/nologin';
        $result = Process::run("sudo /usr/sbin/useradd -m -d {$home} -s {$shell} {$username} 2>&1");

        if ($result->failed()) {
            throw new \RuntimeException("Failed to create system user: " . ($result->errorOutput() ?: $result->output()));
        }

        // Add web servers to user's group so they can read public_html (750)
        // nginx always present; apache only on varnish stack
        $this->addWebServerToGroup($username);

        $result = Process::run("echo '{$password}' | sudo /usr/bin/passwd --stdin {$username} 2>&1");
        if ($result->failed()) {
            Process::run("sudo /usr/sbin/userdel -r {$username}");
            throw new \RuntimeException("Failed to set password: " . ($result->errorOutput() ?: $result->output()));
        }

        Process::run("/usr/bin/chown {$username}:{$username} {$home}");
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
        Process::run("userdel -r {$username} 2>/dev/null");
    }

    protected function createHomeStructure(string $username, string $domain): void
    {
        $home = "{$this->homeBase}/{$username}";

        // Create directories as user
        $dirs = implode(' ', [
            'public_html',
            'public_html/cgi-bin',
            '.ssh',
            'logs',
            'logs/nginx',
            'logs/apache',
            'tmp',
            'mail',
            'backups',
            'private',
            '.openpanel',
        ]);
        ShellService::runAsUser($username, "mkdir -p {$dirs}", 30, $home);

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
        chmod($tmpIndex, 0644);
        ShellService::runAsUser($username, "cp " . escapeshellarg($tmpIndex) . " public_html/index.html", 10, $home);
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
            chmod($tmpHt, 0644);
            ShellService::runAsUser($username, "cp " . escapeshellarg($tmpHt) . " public_html/.htaccess", 10, $home);
            @unlink($tmpHt);
        }

        // Set permissions: home=711, public_html=750, private dirs=700, tmp=1777
        ShellService::runAsUser($username, implode(' && ', [
            'chmod 711 .',                    // home: owner=rwx, group=execute-only (traverse), other=execute-only
            'chmod 750 public_html',           // web-readable but not world-readable
            'chmod 700 .ssh',                  // SSH keys private
            'chmod 700 private',               // private files owner-only
            'chmod 700 backups',               // backups owner-only
            'chmod 700 .openpanel',            // config owner-only
            'chmod 700 tmp',                   // temp files owner-only (not world-writable)
            'chmod 700 logs',                  // logs owner-only
            'chmod 1777 public_html/cgi-bin',  // CGI tmp (sticky)
        ]), 10, $home);
    }

    protected function createDnsZone(string $username, string $domain, string $ip): void
    {
        $serial = date('Ymd') . '01';
        $zoneFile = "{$this->dnsZoneDir}/{$domain}.db";

        $zone = <<<BIND
\$TTL 14400
@   IN  SOA ns1.{$domain}. admin.{$domain}. (
        {$serial}
        3600
        1800
        1209600
        86400
)

@       IN  NS      ns1.{$domain}.
@       IN  NS      ns2.{$domain}.
@       IN  A       {$ip}
ns1     IN  A       {$ip}
ns2     IN  A       {$ip}
www     IN  A       {$ip}
mail    IN  A       {$ip}
ftp     IN  A       {$ip}
@       IN  MX  10  mail.{$domain}.
@       IN  TXT     "v=spf1 +a +mx +ip4:{$ip} ~all"
BIND;

        Process::run("cat > {$zoneFile} <<'ZONEEOF'\n{$zone}\nZONEEOF");
        Process::run("chown named:named {$zoneFile}");
    }

    protected function removeDnsZone(string $username, string $domain): void
    {
        $zoneFile = "{$this->dnsZoneDir}/{$domain}.db";
        Process::run("rm -f {$zoneFile}");
    }

    protected function createNginxVhost(string $username, string $domain): void
    {
        $home = "{$this->homeBase}/{$username}";
        $stack = $this->stackService->getCurrentStack();
        $this->stackService->generateVhostForDomain($stack, $username, $domain, $home);
    }

    protected function removeNginxVhost(string $username): void
    {
        Process::run("rm -f {$this->nginxVhostDir}/{$username}.conf");
    }

    protected function createPhpFpmPool(string $username): void
    {
        $home = "{$this->homeBase}/{$username}";

        $pool = <<<FPM
[{$username}]
user = {$username}
group = {$username}
listen = /run/openpanel-php-user-{$username}.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

pm = ondemand
pm.max_children = 10
pm.process_idle_timeout = 60s
pm.max_requests = 500

; Isolation: open_basedir restricted to user home + per-user tmp
php_admin_value[open_basedir] = {$home}:/tmp
php_admin_value[session.save_path] = {$home}/tmp
php_admin_value[upload_tmp_dir] = {$home}/tmp
php_admin_value[sys_temp_dir] = {$home}/tmp

; Logging
php_admin_value[error_log] = {$home}/logs/php-error.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off

; Security
php_admin_flag[expose_php] = off
php_admin_value[disable_functions] = passthru,system,dl,putenv,pcntl_exec,proc_nice,proc_terminate,proc_close,show_source

; Resource limits
php_value[memory_limit] = 256M
php_value[max_execution_time] = 60
php_value[max_input_time] = 120
php_value[upload_max_filesize] = 64M
php_value[post_max_size] = 64M
php_value[max_file_uploads] = 20
FPM;

        Process::run("mkdir -p {$this->phpFpmPoolDir}");
        Process::run("cat > {$this->phpFpmPoolDir}/{$username}.conf <<'PMEOF'\n{$pool}\nPMEOF");
    }

    protected function removePhpFpmPool(string $username): void
    {
        Process::run("rm -f {$this->phpFpmPoolDir}/{$username}.conf");
    }

    protected function createEmailDomain(string $username, string $domain): void
    {
        $home = "{$this->homeBase}/{$username}";

        // Create mail dir as user
        ShellService::runAsUser($username, "mkdir -p mail/" . escapeshellarg($domain), 10, $home);

        // Write to /etc/postfix/vhost as root (system file)
        $vhostFile = '/etc/postfix/vhost';
        if (file_exists($vhostFile)) {
            $existing = file_get_contents($vhostFile);
            if (strpos($existing, $domain) === false) {
                Process::run("echo " . escapeshellarg($domain) . " >> " . escapeshellarg($vhostFile));
            }
        } else {
            Process::run("echo " . escapeshellarg($domain) . " > " . escapeshellarg($vhostFile));
        }
    }

    protected function removeEmailDomain(string $domain): void
    {
        $vhostFile = '/etc/postfix/vhost';
        if (file_exists($vhostFile)) {
            Process::run("sed -i '/^{$domain}$/d' {$vhostFile}");
        }
    }

    protected function setupFtpUser(string $username, string $password): void
    {
        $home = "{$this->homeBase}/{$username}";

        $result = Process::run("which pure-pw 2>/dev/null");
        if ($result->successful()) {
            $dbFile = '/etc/pureftpd.pdb';
            Process::run(<<<BASH
                echo -e "{$password}\n{$password}" | pure-pw useradd {$username} -u {$username} -d {$home} -f {$dbFile} 2>/dev/null
            BASH);
            Process::run("pure-pw mkdb {$dbFile} 2>/dev/null");
        }
    }

    protected function removeFtpUser(string $username): void
    {
        $result = Process::run("which pure-pw 2>/dev/null");
        if ($result->successful()) {
            $dbFile = '/etc/pureftpd.pdb';
            Process::run("pure-pw userdel {$username} -f {$dbFile} 2>/dev/null");
            Process::run("pure-pw mkdb {$dbFile} 2>/dev/null");
        }
    }

    protected function recordInDatabase(
        string $username,
        string $domain,
        string $ip,
        string $email,
        string $package,
        int $disk,
        int $bandwidth
    ): void {
        DB::connection('mysql')->table('accounts')->insert([
            'username' => $username,
            'domain' => $domain,
            'ip_address' => $ip,
            'email' => $email,
            'package' => $package,
            'disk_limit' => $disk,
            'bandwidth_limit' => $bandwidth,
            'status' => 'active',
            'backup' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function removeFromDatabase(string $username): void
    {
        DB::connection('mysql')->table('accounts')->where('username', $username)->delete();
    }

    protected function updateDatabaseField(string $username, string $field, $value): void
    {
        DB::connection('mysql')->table('accounts')->where('username', $username)->update([
            $field => $value,
            'updated_at' => now(),
        ]);
    }

    protected function reloadServices(): void
    {
        $stack = $this->stackService->getActiveStack();

        $this->stackService->reloadStack($stack);

        Process::run("systemctl reload named 2>/dev/null || systemctl restart named 2>/dev/null");
        Process::run("systemctl reload postfix 2>/dev/null || systemctl restart postfix 2>/dev/null");
    }
}
