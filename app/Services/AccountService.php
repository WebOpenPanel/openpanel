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

        Process::run("passwd -l {$username}");
        Process::run("chage -E 0 {$username}");

        $this->updateDatabaseField($username, 'status', 'suspended');

        $this->reloadServices();

        return ['success' => true, 'message' => "Account '{$username}' suspended."];
    }

    public function unsuspend(string $username): array
    {
        $user = $this->getUser($username);
        if (!$user) {
            throw new \RuntimeException("User '{$username}' not found.");
        }

        Process::run("passwd -u {$username}");
        Process::run("chage -E -1 {$username}");

        $this->updateDatabaseField($username, 'status', 'active');

        $this->reloadServices();

        return ['success' => true, 'message' => "Account '{$username}' unsuspended."];
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

        $result = Process::run("sudo /usr/sbin/useradd -m -d {$home} -s /bin/bash {$username} 2>&1");

        if ($result->failed()) {
            throw new \RuntimeException("Failed to create system user: " . ($result->errorOutput() ?: $result->output()));
        }

        $result = Process::run("echo '{$password}' | sudo /usr/bin/passwd --stdin {$username} 2>&1");
        if ($result->failed()) {
            Process::run("sudo /usr/sbin/userdel -r {$username}");
            throw new \RuntimeException("Failed to set password: " . ($result->errorOutput() ?: $result->output()));
        }

        Process::run("/usr/bin/chown {$username}:{$username} {$home}");
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
            'tmp',
            'mail',
            'backups',
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

        // Write .htaccess via temp file
        $htaccess = <<<HTACCESS
# Disable directory listing
Options -Indexes

# Protect .openpanel files
<FilesMatch "^\.openpanel">
    Order allow,deny
    Deny from all
</FilesMatch>
HTACCESS;

        $tmpHt = tempnam(sys_get_temp_dir(), 'ht');
        file_put_contents($tmpHt, $htaccess);
        chmod($tmpHt, 0644);
        ShellService::runAsUser($username, "cp " . escapeshellarg($tmpHt) . " public_html/.htaccess", 10, $home);
        @unlink($tmpHt);

        // Set permissions as user (owner can chmod own files)
        ShellService::runAsUser($username, "chmod 711 . && chmod 755 public_html && chmod 700 .ssh && chmod 1777 tmp", 10, $home);
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

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/openpanel-php-user-{$username}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location /cgi-bin/ {
        gzip off;
        root {$home}/public_html;
        include fastcgi_params;
    }
}
NGINX;

        Process::run("mkdir -p {$this->nginxVhostDir}");
        Process::run("cat > {$this->nginxVhostDir}/{$username}.conf <<'VHOSTEOF'\n{$vhost}\nVHOSTEOF");
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

php_admin_value[error_log] = {$home}/logs/php-error.log
php_admin_flag[log_errors] = on
php_value[memory_limit] = 256M
php_value[max_execution_time] = 60
php_value[upload_max_filesize] = 64M
php_value[post_max_size] = 64M
php_value[open_basedir] = {$home}:/tmp
php_value[session.save_path] = {$home}/tmp
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
