<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AccountService
{
    const RESERVED_USERNAMES = ['global', 'admin', 'root', 'php', 'exec'];
    const QUOTA_PART_CONF = '/usr/local/openpanel/.conf/quota_part.conf';
    const QUOTA_TYPE_CONF = '/usr/local/openpanel/.conf/quota_type.conf';
    const CONF_PATH = '/usr/local/openpanel/.conf/openpanel.conf';
    const PRO_CONF = '/usr/local/openpanel/.conf/.openpanel_pro.conf';
    const INI_PATH = '/usr/local/openpanel/.conf/openpanel.ini';
    const MAX_FREE_ACCOUNTS = 10;

    public static function createAccount(
        string $domain,
        string $username,
        string $password,
        string $email,
        ?string $packageId,
        string $serverIp,
        string $shell = '/bin/bash',
        ?string $inode = null,
        ?string $limitNproc = null,
        ?string $limitNofile = null,
        bool $autoSsl = true,
        string $lang = 'en'
    ): array {
        $domain = strtolower(trim($domain));
        $username = strtolower(trim($username));
        $password = trim($password);
        $email = trim($email);

        $validation = self::validateAccount($domain, $username);
        if ($validation !== true) return $validation;

        $nattedIp = IpService::natIp($serverIp) ?: $serverIp;
        $package = self::getPackage($packageId);
        if (!$package) return ['status' => 'Error', 'message' => 'Package not found'];

        $diskQuota = $package['disk_quota'] ?? 0;
        $bandwidth = $package['bandwidth'] ?? 0;

        ShellService::exec("useradd -d /home/{$username} -s {$shell} {$username} 2>&1");
        ShellService::exec("echo '{$password}' | passwd --stdin {$username} 2>&1");

        $dirs = [
            "/home/{$username}",
            "/home/{$username}/public_html",
            "/home/{$username}/tmp",
            "/home/{$username}/logs",
            "/home/{$username}/.conf",
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                ShellService::exec("mkdir -p {$dir}");
            }
        }
        ShellService::exec("chown -R {$username}:{$username} /home/{$username}");

        if ($shell === '/bin/bash') {
            ShellService::exec("usermod -a -G sshusers {$username} 2>/dev/null");
        }

        DnsService::addZoneForce($domain, $nattedIp, $email, 'none');

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        try {
            DB::connection('openpanel')->table('user')->insert([
                'username' => $username,
                'password' => $passwordHash,
                'email' => $email,
                'domain' => $domain,
                'ip_address' => $serverIp,
                'package' => $packageId ?? '',
                'backup' => 1,
                'suspended' => 'no',
                'suspended_details' => '',
                'server_email' => $email,
                'shell' => $shell,
                'autossl' => $autoSsl ? 'on' : 'off',
                'inode' => $inode ?? '',
                'limit_nproc' => $limitNproc ?? '',
                'limit_nofile' => $limitNofile ?? '',
                'uid' => ShellService::exec("id -u {$username} 2>/dev/null"),
            ]);
        } catch (\Exception $e) {
            return ['status' => 'Error', 'message' => 'Database error: ' . $e->getMessage()];
        }

        try {
            DB::connection('openpanel')->statement("CREATE DATABASE IF NOT EXISTS `{$username}`");
            DB::connection('openpanel')->statement("CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY " . DB::connection('openpanel')->getPdo()->quote($password));
            DB::connection('openpanel')->statement("GRANT ALL PRIVILEGES ON `{$username}`.* TO '{$username}'@'localhost'");
            DB::connection('openpanel')->statement("FLUSH PRIVILEGES");
        } catch (\Exception $e) {}

        $webserverConfig = WebServerService::webServerConfiguration();
        $arrayData = [
            'username' => $username,
            'domain' => $domain,
            'ip-address' => $nattedIp,
            'email' => $email,
            'path' => "/home/{$username}/public_html",
        ];
        $arrayData['apache_port'] = $webserverConfig['apache_port_nonssl'];
        $arrayData['nginx_port'] = $webserverConfig['nginx_port_nonssl'];
        if (!empty($webserverConfig['varnish_port'])) {
            $arrayData['varnish_port'] = $webserverConfig['varnish_port'];
        }
        WebServerService::webServersRebuild($arrayData);

        ServerService::quotaSet($username, (int) $diskQuota, (int) $diskQuota);

        if (!empty($limitNproc) || !empty($limitNofile)) {
            $limits = [];
            if (!empty($limitNproc)) $limits['nproc'] = $limitNproc;
            if (!empty($limitNofile)) $limits['nofile'] = $limitNofile;
            ServerService::userSetLimits($username, $limits);
        } else {
            ServerService::userSetLimits($username, [
                'nproc' => $package['limit_nproc'] ?? '65535',
                'nofile' => $package['limit_nofile'] ?? '65535',
            ]);
        }

        if (!empty($package['bandwidth']) && $package['bandwidth'] > 0) {
            ShellService::exec("mkdir -p /var/log/openpanel/domains/{$domain}");
            ShellService::exec("chown -R {$username}:{$username} /var/log/openpanel/domains/{$domain}");
        }

        if ($autoSsl) {
            WebServerService::autoSslIssue($domain, $username, 2048, 'www');
        }

        self::setDefaultLanguage($username, $lang);

        ServerService::manageServices('reload', ['httpd', 'nginx']);

        return ['status' => 'OK', 'message' => "Account {$username} created successfully", 'username' => $username, 'domain' => $domain];
    }

    public static function validateAccount(string $domain, string $username): true|array
    {
        if (in_array($username, self::RESERVED_USERNAMES)) {
            return ['status' => 'Error', 'message' => 'Username is a reserved word'];
        }

        $existingDomain = DB::connection('openpanel')->table('user')->where('domain', $domain)->first();
        if ($existingDomain) {
            return ['status' => 'Error', 'message' => 'Domain already exists'];
        }

        $existingAddon = DB::connection('openpanel')->table('domains')->where('domain', $domain)->first();
        if ($existingAddon) {
            return ['status' => 'Error', 'message' => 'Domain already exists as addon domain'];
        }

        $existingUser = DB::connection('openpanel')->table('user')->where('username', $username)->first();
        if ($existingUser) {
            return ['status' => 'Error', 'message' => 'Username already exists'];
        }

        $hostname = trim(ShellService::exec('hostname -f'));
        $hostnameShort = trim(ShellService::exec('hostname'));
        if ($domain === $hostname || $domain === $hostnameShort) {
            return ['status' => 'Error', 'message' => "Domain {$domain} is used by the hostname"];
        }

        $passwdCheck = ShellService::exec("grep '^{$username}:' /etc/passwd");
        if (!empty(trim($passwdCheck))) {
            return ['status' => 'Error', 'message' => "Username {$username} exists in /etc/passwd"];
        }

        if (is_dir("/home/{$username}/")) {
            return ['status' => 'Error', 'message' => "Home folder /home/{$username}/ already exists"];
        }

        return true;
    }

    public static function getPackage(?string $packageId): ?array
    {
        if (!$packageId) return null;
        try {
            $row = DB::connection('openpanel')->table('packages')->where('id', $packageId)->first();
            return $row ? (array) $row : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function suspendAccount(string $username, string $reason = ''): bool
    {
        $user = DB::connection('openpanel')->table('user')->where('username', $username)->first();
        if (!$user) return false;

        $domain = $user->domain;
        $suspendedPath = "/usr/local/openpanel/users/suspended/{$username}";

        ShellService::exec("mkdir -p {$suspendedPath}");
        ShellService::exec("touch {$suspendedPath}");
        if (!empty($reason)) {
            ShellService::writeFile("{$suspendedPath}.reason", $reason);
        }

        ShellService::exec("chown -R root:root /home/{$username}/public_html");

        DB::connection('openpanel')->table('user')->where('username', $username)->update(['suspended' => 'yes', 'suspended_details' => $reason]);

        $arrayData = ['username' => $username, 'domain' => $domain];
        WebServerService::webServersRebuild($arrayData);

        ServerService::manageServices('reload', ['httpd', 'nginx']);

        return true;
    }

    public static function unsuspendAccount(string $username): bool
    {
        $user = DB::connection('openpanel')->table('user')->where('username', $username)->first();
        if (!$user) return false;

        $domain = $user->domain;
        $suspendedPath = "/usr/local/openpanel/users/suspended/{$username}";

        if (is_file($suspendedPath)) {
            ShellService::exec("rm -f {$suspendedPath}");
        }
        if (is_file("{$suspendedPath}.reason")) {
            ShellService::exec("rm -f {$suspendedPath}.reason");
        }
        if (is_file("{$suspendedPath}.bandwidth")) {
            ShellService::exec("rm -f {$suspendedPath}.bandwidth");
        }

        ShellService::exec("chown -R {$username}:{$username} /home/{$username}/public_html");

        DB::connection('openpanel')->table('user')->where('username', $username)->update(['suspended' => 'no', 'suspended_details' => '']);

        $arrayData = ['username' => $username, 'domain' => $domain];
        WebServerService::webServersRebuild($arrayData);

        ServerService::manageServices('reload', ['httpd', 'nginx']);

        return true;
    }

    public static function deleteAccount(string $username, bool $keepDns = false): bool
    {
        $user = DB::connection('openpanel')->table('user')->where('username', $username)->first();
        if (!$user) return false;

        $domain = $user->domain;

        if (!$keepDns) {
            DnsService::deleteZone($domain);
        }

        try {
            DB::connection('openpanel')->statement("DROP DATABASE IF EXISTS `{$username}`");
            DB::connection('openpanel')->statement("DROP USER IF EXISTS '{$username}'@'localhost'");
            DB::connection('openpanel')->statement("FLUSH PRIVILEGES");
        } catch (\Exception $e) {}

        ShellService::exec("userdel -f {$username} 2>/dev/null");
        ShellService::exec("groupdel {$username} 2>/dev/null");

        $arrayData = ['username' => $username, 'domain' => $domain];
        WebServerService::webServersRebuild($arrayData);

        IpService::deleteIpNatUser($username);

        ShellService::exec("rm -rf /home/{$username} 2>/dev/null");

        $quotaPart = self::getQuotaPart();
        $quotaType = self::getQuotaType();
        ShellService::exec("setquota -u -F {$quotaType} {$username} 0 0 0 0 {$quotaPart} 2>/dev/null");

        ShellService::exec("sed -i '/^{$username}/d' /etc/security/limits.d/{$username}.conf 2>/dev/null");
        ShellService::exec("rm -f /etc/security/limits.d/{$username}.conf 2>/dev/null");

        DB::connection('openpanel')->table('user')->where('username', $username)->delete();
        DB::connection('openpanel')->table('domains')->where('user', $username)->delete();
        DB::connection('openpanel')->table('subdomains')->where('user', $username)->delete();

        ServerService::manageServices('reload', ['httpd', 'nginx']);

        return true;
    }

    public static function changePassword(string $username, string $newPassword): bool
    {
        $user = DB::connection('openpanel')->table('user')->where('username', $username)->first();
        if (!$user) return false;

        ShellService::exec("echo '{$newPassword}' | passwd --stdin {$username} 2>&1");

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        DB::connection('openpanel')->table('user')->where('username', $username)->update(['password' => $passwordHash]);

        return true;
    }

    public static function changeOwner(string $username, string $newOwner): bool
    {
        $user = DB::connection('openpanel')->table('user')->where('username', $username)->first();
        if (!$user) return false;

        DB::connection('openpanel')->table('user')->where('username', $username)->update(['reseller' => $newOwner]);

        $resellerDir = "/home/{$newOwner}/.conf/reseller";
        if (!is_dir($resellerDir)) {
            ShellService::exec("mkdir -p {$resellerDir}");
        }

        return true;
    }

    public static function checkBandwidth(string $username): array
    {
        $user = DB::connection('openpanel')->table('user')->where('username', $username)->first();
        if (!$user) return ['status' => 'error', 'message' => 'User not found'];

        $package = self::getPackage($user->package);
        $bandwidthLimit = $package['bandwidth'] ?? 0;

        if ($bandwidthLimit <= 0) return ['status' => 'ok', 'used' => 0, 'limit' => 0];

        $domain = $user->domain;
        $logDir = "/var/log/openpanel/domains/{$domain}";
        $bwFile = "{$logDir}/bandwidth";

        $used = 0;
        if (file_exists($bwFile)) {
            $used = (int) trim(file_get_contents($bwFile));
        }

        if ($used >= $bandwidthLimit) {
            self::suspendAccount($username, 'Bandwidth limit exceeded');
            return ['status' => 'exceeded', 'used' => $used, 'limit' => $bandwidthLimit];
        }

        return ['status' => 'ok', 'used' => $used, 'limit' => $bandwidthLimit];
    }

    protected static function setDefaultLanguage(string $username, string $lang): void
    {
        $userConfDir = "/home/{$username}/.conf";
        if (!is_dir($userConfDir)) {
            ShellService::exec("mkdir -p {$userConfDir}");
            ShellService::exec("chown {$username}:{$username} {$userConfDir}");
        }

        $panelIni = parse_ini_file(self::INI_PATH) ?: ['THEME' => 'original', 'LANGUSER' => 'en'];

        $iniFile = "{$userConfDir}/openpanel.ini";
        $content = "THEME=" . ($panelIni['THEME'] ?? 'original') . "\n";
        $content .= "LANG=" . (empty($lang) ? ($panelIni['LANGUSER'] ?? 'en') : substr(strtolower(trim($lang)), 0, 2)) . "\n";
        file_put_contents($iniFile, $content);
        ShellService::exec("chown {$username}:{$username} {$iniFile}");
    }

    protected static function getQuotaPart(): string
    {
        if (file_exists(self::QUOTA_PART_CONF)) {
            $part = trim(file_get_contents(self::QUOTA_PART_CONF));
            return empty($part) ? '/' : $part;
        }
        return '/';
    }

    protected static function getQuotaType(): string
    {
        if (file_exists(self::QUOTA_TYPE_CONF)) {
            $type = trim(file_get_contents(self::QUOTA_TYPE_CONF));
            return empty($type) ? 'vfsv0' : $type;
        }
        return 'vfsv0';
    }
}
