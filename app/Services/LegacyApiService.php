<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\UserAccount;

class LegacyApiService
{
    public static function handleRequest(string $key, string $action, array $params): array
    {
        $keyValid = self::validateKey($key);
        if (!$keyValid) {
            return ['status' => 'error', 'message' => 'Key NOT found'];
        }

        return match ($action) {
            'get_list_of_users' => self::getListOfUsers(),
            'get_quota' => self::getQuota($params),
            'get_domains' => self::getDomains($params),
            'get_subdomains' => self::getSubdomains($params),
            'get_mysql_databases' => self::getMysqlDatabases($params),
            'get_mysql_users' => self::getMysqlUsers($params),
            'get_root_email' => self::getRootEmail(),
            'create_ftp_user' => self::createFtpUser($params),
            'create_mysql_database' => self::createMysqlDatabase($params),
            'create_mysql_user' => self::createMysqlUser($params),
            'del_mysql_user' => self::deleteMysqlUser($params),
            'del_mysql_database' => self::deleteMysqlDatabase($params),
            'get_srv_type' => self::getServerType(),
            'xml' => self::getXml($params),
            default => ['status' => 'error', 'message' => 'Unknown action'],
        };
    }

    public static function validateKey(string $key): bool
    {
        if (empty($key)) return false;
        $keysFile = '/usr/local/openpanel/.conf/.api_keys';
        if (!file_exists($keysFile)) return false;
        $output = ShellService::exec("grep " . escapeshellarg($key) . " " . $keysFile . " | awk -F : '{print $3}' 2>&1");
        return trim($output) === '1';
    }

    private static function getListOfUsers(): array
    {
        $users = DB::table('user')->pluck('username')->toArray();
        return ['status' => 'success', 'data' => $users];
    }

    private static function getQuota(array $params): array
    {
        $username = $params['username'] ?? '';
        if (empty($username)) return ['status' => 'error', 'message' => 'Username required'];
        $repquota = ShellService::exec("/usr/sbin/repquota -a | grep '^{$username} ' | awk '{print $3,$5}'");
        if (empty(trim($repquota))) return ['status' => 'success', 'used' => 0, 'limit' => 0];
        $parts = explode(' ', trim($repquota));
        return ['status' => 'success', 'used' => round(($parts[0] ?? 0) / 1024), 'limit' => round(($parts[1] ?? 0) / 1024)];
    }

    private static function getDomains(array $params): array
    {
        $username = $params['username'] ?? '';
        if (empty($username)) return ['status' => 'error', 'message' => 'Username required'];
        $account = DB::table('user')->where('username', $username)->first();
        $domains = [];
        if ($account) {
            $domains[] = ['domain' => $account->domain, 'path' => '/home/' . $username . '/public_html'];
            $addonDomains = DB::table('domains')->where('user', $username)->get();
            foreach ($addonDomains as $d) {
                $domains[] = ['domain' => $d->domain, 'path' => $d->path ?? '/home/' . $username . '/' . $d->domain];
            }
        }
        return ['status' => 'success', 'data' => $domains];
    }

    private static function getSubdomains(array $params): array
    {
        $username = $params['username'] ?? '';
        if (empty($username)) return ['status' => 'error', 'message' => 'Username required'];
        $account = DB::table('user')->where('username', $username)->first();
        $subdomains = [];
        if ($account) {
            $subs = DB::table('subdomains')->where('domain', $account->domain)->get();
            foreach ($subs as $s) {
                $subdomains[] = ['subdomain' => $s->subdomain . '.' . $s->domain, 'path' => $s->path ?? '/home/' . $username . '/' . $s->subdomain];
            }
        }
        return ['status' => 'success', 'data' => $subdomains];
    }

    private static function getMysqlDatabases(array $params): array
    {
        $username = $params['username'] ?? '';
        if (empty($username)) return ['status' => 'error', 'message' => 'Username required'];
        $result = DB::select("SHOW DATABASES LIKE '{$username}_%'");
        $databases = array_map(fn($row) => (array) $row, $result);
        return ['status' => 'success', 'data' => $databases];
    }

    private static function getMysqlUsers(array $params): array
    {
        $username = $params['username'] ?? '';
        if (empty($username)) return ['status' => 'error', 'message' => 'Username required'];
        $result = DB::select("SELECT User, Host FROM mysql.user WHERE User LIKE '{$username}_%'");
        $users = array_map(fn($row) => ['user' => $row->User, 'host' => $row->Host], $result);
        return ['status' => 'success', 'data' => $users];
    }

    private static function getRootEmail(): array
    {
        $settings = DB::table('settings')->where('root_name', 'root')->first();
        return ['status' => 'success', 'email' => $settings->root_email ?? ''];
    }

    private static function createFtpUser(array $params): array
    {
        $ftpUser = $params['ftp_username'] ?? '';
        $ftpPass = $params['ftp_password'] ?? '';
        $panelUser = $params['openpanel_user'] ?? '';
        $domain = $params['domain'] ?? '';
        if (empty($ftpUser) || empty($ftpPass) || empty($panelUser) || empty($domain)) {
            return ['status' => 'error', 'message' => 'Missing required params: ftp_username, ftp_password, panel_user, domain'];
        }
        $ftpPath = '/home/' . $ftpUser;
        ShellService::exec("(echo " . escapeshellarg($ftpPass) . "; echo " . escapeshellarg($ftpPass) . ") | pure-pw useradd " . escapeshellarg($ftpUser . '@' . $domain) . " -u " . escapeshellarg($panelUser) . " -g " . escapeshellarg($panelUser) . " -d " . escapeshellarg($ftpPath) . " -m");
        return ['status' => 'success', 'message' => 'FTP user created'];
    }

    private static function createMysqlDatabase(array $params): array
    {
        $dbname = $params['dbname'] ?? '';
        if (empty($dbname)) return ['status' => 'error', 'message' => 'Database name required'];
        DB::statement("CREATE DATABASE `{$dbname}`");
        return ['status' => 'success', 'message' => 'Database created'];
    }

    private static function createMysqlUser(array $params): array
    {
        $dbname = $params['dbname'] ?? '';
        $dbuser = $params['dbuser'] ?? '';
        $dbpass = $params['dbpassword'] ?? '';
        $dbhost = $params['dbhost'] ?? 'localhost';
        if (empty($dbname) || empty($dbuser) || empty($dbpass)) {
            return ['status' => 'error', 'message' => 'Missing required params: dbname, dbuser, dbpassword'];
        }
        $parts = explode('_', $dbname, 2);
        $escapedDb = $parts[0] . '\_' . ($parts[1] ?? '');
        DB::statement("GRANT ALL ON `{$escapedDb}`.* TO '{$dbuser}'@'{$dbhost}' IDENTIFIED BY '" . addslashes($dbpass) . "'");
        DB::statement('FLUSH PRIVILEGES');
        return ['status' => 'success', 'message' => 'MySQL user created and granted'];
    }

    private static function deleteMysqlUser(array $params): array
    {
        $dbuser = $params['dbuser'] ?? '';
        $host = $params['host'] ?? 'localhost';
        if (empty($dbuser)) return ['status' => 'error', 'message' => 'Database user required'];
        DB::statement("DROP USER '{$dbuser}'@'{$host}'");
        DB::statement('FLUSH PRIVILEGES');
        return ['status' => 'success', 'message' => 'MySQL user deleted'];
    }

    private static function deleteMysqlDatabase(array $params): array
    {
        $dbname = $params['dbname'] ?? '';
        if (empty($dbname)) return ['status' => 'error', 'message' => 'Database name required'];
        DB::statement("DROP DATABASE `{$dbname}`");
        return ['status' => 'success', 'message' => 'Database deleted'];
    }

    private static function getServerType(): array
    {
        if (!file_exists('/usr/sbin/virt-what')) {
            ShellService::exec('yum -y install virt-what 2>/dev/null');
        }
        $vps = trim(ShellService::exec('virt-what 2>/dev/null'));
        return ['status' => 'success', 'type' => empty($vps) ? 'Dedicated' : $vps];
    }

    private static function getXml(array $params): array
    {
        $username = $params['username'] ?? '';
        if (empty($username)) return ['status' => 'error', 'message' => 'Username required'];
        $account = DB::table('user')->where('username', $username)->first();
        if (!$account) return ['status' => 'error', 'message' => 'Account not found'];
        $xml = ['domains' => [['name' => $account->domain, 'path' => '/home/' . $username . '/public_html']]];
        $addonDomains = DB::table('domains')->where('user', $username)->get();
        foreach ($addonDomains as $d) $xml['domains'][] = ['name' => $d->domain, 'path' => $d->path ?? ''];
        $subs = DB::table('subdomains')->where('domain', $account->domain)->get();
        $xml['subdomains'] = [];
        foreach ($subs as $s) $xml['subdomains'][] = ['name' => $s->subdomain . '.' . $s->domain, 'path' => $s->path ?? ''];
        $dbs = DB::select("SHOW DATABASES LIKE '{$username}_%'");
        $xml['databases'] = array_map(fn($r) => (array_values((array) $r))[0], $dbs);
        return ['status' => 'success', 'data' => $xml];
    }
}
