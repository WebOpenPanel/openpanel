<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\WordPressSite;
use App\Services\AccountService;
use App\Services\ApiTokenService;
use App\Services\DnsService;
use App\Services\EmailService;
use App\Services\MysqlService;
use App\Services\SslService;
use App\Services\WordPressService;
use App\Services\WebStackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class V1Controller extends Controller
{
    private function token(Request $request): ApiToken
    {
        return $request->get('_api_token');
    }

    private function ok(mixed $data = [], int $code = 200): JsonResponse
    {
        return response()->json(array_merge(['success' => true], (array) $data), $code);
    }

    private function fail(string $error, int $code = 400): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $error], $code);
    }

    private function scope(Request $request, string $scope): ?JsonResponse
    {
        $denied = ApiTokenService::enforceScope($this->token($request), $scope);
        return $denied;
    }

    // â”€â”€â”€ Health / Server â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function health(): JsonResponse
    {
        return $this->ok(['status' => 'ok', 'version' => '1.0.0']);
    }

    public function abuseMonitor(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'admin:all')) return $r;

        $result = \App\Services\AbuseMonitorService::scan();
        return $this->ok($result);
    }

    public function serverInfo(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:read')) return $r;

        $hostname = gethostname();
        $ip = trim(shell_exec('hostname -I | awk \'{print $1}\'') ?: '127.0.0.1');
        $os = trim(shell_exec('cat /etc/redhat-release 2>/dev/null || cat /etc/os-release 2>/dev/null | head -1') ?: 'Unknown');
        $php = phpversion();
        $stack = (new WebStackService())->getActiveStack();

        $accountCount = DB::connection('mysql')->table('accounts')->count();
        $wpCount = 0;
        try { $wpCount = DB::connection('mysql')->table('wordpress_sites')->whereNull('deleted_at')->count(); } catch (\Exception $e) {}

        return $this->ok([
            'hostname' => $hostname,
            'ip' => $ip,
            'os' => $os,
            'php_version' => $php,
            'active_stack' => $stack,
            'accounts' => $accountCount,
            'wordpress_sites' => $wpCount,
        ]);
    }

    // â”€â”€â”€ Packages â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function packages(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:read')) return $r;

        $packages = DB::connection('mysql')->table('packages')->get();
        return $this->ok(['packages' => $packages]);
    }

    // â”€â”€â”€ Accounts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function accountCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:create')) return $r;

        $request->validate([
            'username' => 'required|string|max:32',
            'password' => 'required|string|min:8',
            'domain' => 'required|string|max:255',
            'package' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        // Reseller can only create in their own scope
        $token = $this->token($request);
        if ($token->isReseller()) {
            $request->merge(['reseller' => $token->reseller_username]);
        }

        try {
            $svc = new AccountService();
            $result = $svc->create([
                'username' => $request->username,
                'password' => $request->password,
                'domain' => $request->domain,
                'package' => $request->package ?? 'default',
                'email' => $request->email,
            ]);
            return $this->ok($result, 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function accountSuspend(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:suspend')) return $r;
        if ($r = $this->resellerGuard($request, $username)) return $r;

        try {
            $result = (new AccountService())->suspend($username);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function accountUnsuspend(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:unsuspend')) return $r;
        if ($r = $this->resellerGuard($request, $username)) return $r;

        try {
            $result = (new AccountService())->unsuspend($username);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function accountTerminate(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:terminate')) return $r;
        if ($r = $this->resellerGuard($request, $username)) return $r;

        if (!$request->boolean('confirm')) {
            return $this->fail('Termination requires confirm=true', 400);
        }

        try {
            $result = (new AccountService())->delete($username);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function accountChangePassword(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:update')) return $r;
        if ($r = $this->resellerGuard($request, $username)) return $r;

        $request->validate(['password' => 'required|string|min:8']);

        try {
            $result = (new AccountService())->changePassword($username, $request->password);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function accountChangePackage(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:update')) return $r;
        if ($r = $this->resellerGuard($request, $username)) return $r;

        $request->validate(['package' => 'required|string']);

        try {
            DB::connection('mysql')->table('accounts')
                ->where('username', $username)
                ->update(['package' => $request->package, 'updated_at' => now()]);
            return $this->ok(['message' => "Package changed to '{$request->package}' for '{$username}'"]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function accountRepairIsolation(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'admin:all')) return $r;

        try {
            $result = (new AccountService())->repairUserIsolation($username);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function accountResourceLimits(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'admin:all')) return $r;

        $user = (new AccountService())->getUser($username);
        if (!$user) {
            return $this->fail('Account not found', 404);
        }

        $usage = \App\Services\ResourceControlService::getUsage($username);
        $package = $user['package'] ?? 'default';
        $pkgModel = \App\Models\Package::where('name', $package)->first();

        return $this->ok([
            'username' => $username,
            'package' => $package,
            'limits' => $pkgModel ? [
                'disk_space_mb' => $pkgModel->disk_space_mb,
                'nproc' => $pkgModel->nproc,
                'nofile' => $pkgModel->nofile,
                'max_domains' => $pkgModel->max_domains,
                'max_email_accounts' => $pkgModel->max_email_accounts,
                'max_databases' => $pkgModel->max_databases,
                'max_ftp_accounts' => $pkgModel->max_ftp_accounts,
                'max_cron_jobs' => $pkgModel->max_cron_jobs,
                'hourly_emails' => $pkgModel->hourly_emails,
                'cgroups' => $pkgModel->cgroups,
            ] : null,
            'usage' => $usage,
        ]);
    }

    public function accountGet(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:read')) return $r;
        if ($r = $this->resellerGuard($request, $username)) return $r;

        $user = (new AccountService())->getUser($username);
        if (!$user) {
            return $this->fail('Account not found', 404);
        }

        unset($user['db_password'], $user['password']);
        return $this->ok(['account' => $user]);
    }

    public function accountUsage(Request $request, string $username): JsonResponse
    {
        if ($r = $this->scope($request, 'accounts:read')) return $r;
        if ($r = $this->resellerGuard($request, $username)) return $r;

        $disk = trim(shell_exec("du -sm /home/{$username} 2>/dev/null | awk '{print $1}'") ?: '0');
        $bandwidth = '0'; // placeholder

        return $this->ok([
            'username' => $username,
            'disk_mb' => (int) $disk,
            'bandwidth_mb' => (int) $bandwidth,
        ]);
    }

    // â”€â”€â”€ WordPress â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function wpInstall(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $request->validate([
            'user_account_id' => 'required|integer',
            'domain' => 'required|string',
            'site_title' => 'nullable|string',
            'admin_user' => 'nullable|string',
            'admin_password' => 'nullable|string',
            'admin_email' => 'nullable|email',
        ]);

        try {
            $result = (new WordPressService())->installWordPress($request->only([
                'user_account_id', 'domain', 'site_title', 'admin_user', 'admin_password', 'admin_email',
            ]));
            return $this->ok($result, 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpEnableRedis(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->enableRedis($site);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpEnableVarnish(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->configureVarnishRules($site, true);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpApplyProfile(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $request->validate(['profile' => 'required|string']);

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->applyPerformanceProfile($site, $request->profile);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpBackup(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->createBackup($site);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpRestore(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $request->validate(['backup_id' => 'required|integer']);

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->restoreSite($site, $request->backup_id);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpStagingCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->createStaging($site);
            return $this->ok($result, 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpCachePurge(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->purgeCache($site);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpGet(Request $request, int $siteId): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = WordPressSite::find($siteId);
        if (!$site) return $this->fail('Site not found', 404);

        $data = $site->toArray();
        unset($data['db_password_encrypted']);
        return $this->ok(['site' => $data]);
    }

    // â”€â”€â”€ DNS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function dnsZoneCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'dns:manage')) return $r;

        $request->validate(['domain' => 'required|string', 'ip' => 'nullable|ip']);

        try {
            $ip = $request->ip ?? trim(shell_exec('hostname -I | awk \'{print $1}\'') ?: '127.0.0.1');
            $email = \App\Services\DnsService::dnsEmail($request->domain);
            $result = \App\Services\DnsService::addZone($request->domain, $ip, $email);
            return $this->ok(['created' => $result], 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function dnsRecordCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'dns:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'type' => 'required|string',
            'name' => 'required|string',
            'value' => 'required|string',
            'ttl' => 'nullable|integer',
        ]);

        try {
            // Add record by saving to zone file via DB
            $zone = DB::connection('mysql')->table('dns_zones')->where('domain', $request->domain)->first();
            if (!$zone) return $this->fail('Zone not found', 404);

            DB::connection('mysql')->table('dns_records')->insert([
                'zone_id' => $zone->id,
                'name' => $request->name,
                'type' => $request->type,
                'value' => $request->value,
                'ttl' => $request->ttl ?? 14400,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \App\Services\DnsService::reloadZone($request->domain);
            return $this->ok(['message' => 'Record added'], 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function dnsRecordDelete(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'dns:manage')) return $r;

        $request->validate(['record_id' => 'required|integer']);

        try {
            $record = DB::connection('mysql')->table('dns_records')->find($request->record_id);
            if (!$record) return $this->fail('Record not found', 404);

            DB::connection('mysql')->table('dns_records')->where('id', $request->record_id)->delete();

            $zone = DB::connection('mysql')->table('dns_zones')->find($record->zone_id);
            if ($zone) \App\Services\DnsService::reloadZone($zone->domain);

            return $this->ok(['message' => 'Record deleted']);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function dnsZoneGet(Request $request, string $domain): JsonResponse
    {
        if ($r = $this->scope($request, 'dns:manage')) return $r;

        $zone = DB::connection('mysql')->table('dns_zones')->where('domain', $domain)->first();
        if (!$zone) return $this->fail('Zone not found', 404);

        $records = DB::connection('mysql')->table('dns_records')->where('zone_id', $zone->id)->get();
        return $this->ok(['zone' => $zone, 'records' => $records]);
    }

    // â”€â”€â”€ Email â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function emailCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        try {
            $email = $request->username . '@' . $request->domain;
            $result = Process::run("doveadm pw -s SHA512-CRYPT -p '{$request->password}' 2>/dev/null");
            $hash = trim($result->output());

            DB::connection('mysql')->table('email_accounts')->insert([
                'domain' => $request->domain,
                'username' => $request->username,
                'password' => $hash,
                'quota' => $request->quota ?? 250,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create maildir
            $maildir = "/var/vmail/{$request->domain}/{$request->username}";
            Process::run("maildirmake {$maildir} 2>/dev/null || mkdir -p {$maildir}");
            Process::run("chown -R vmail:vmail /var/vmail/{$request->domain}");

            return $this->ok(['email' => $email, 'message' => 'Account created'], 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailDelete(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['email' => 'required|email']);

        try {
            [$username, $domain] = explode('@', $request->email);
            DB::connection('mysql')->table('email_accounts')
                ->where('domain', $domain)->where('username', $username)->delete();

            // Remove maildir
            Process::run("rm -rf /var/vmail/{$domain}/{$username}");

            return $this->ok(['message' => 'Account deleted']);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailPassword(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['email' => 'required|email', 'password' => 'required|string|min:6']);

        try {
            [$username, $domain] = explode('@', $request->email);
            $result = Process::run("doveadm pw -s SHA512-CRYPT -p '{$request->password}' 2>/dev/null");
            $hash = trim($result->output());

            DB::connection('mysql')->table('email_accounts')
                ->where('domain', $domain)->where('username', $username)
                ->update(['password' => $hash, 'updated_at' => now()]);

            return $this->ok(['message' => 'Password changed']);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailList(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $query = DB::connection('mysql')->table('email_accounts');
        if ($request->domain) {
            $query->where('domain', $request->domain);
        }
        $accounts = $query->select('id', 'domain', 'username', 'quota', 'created_at')->get();
        return $this->ok(['accounts' => $accounts]);
    }

    // â”€â”€â”€ Database â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function dbCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'database:manage')) return $r;

        $request->validate(['name' => 'required|string']);

        try {
            \App\Services\MysqlService::createDatabase($request->name);

            if ($request->username) {
                \App\Services\MysqlService::createDatabaseUser($request->username, $request->password ?? 'changeme');
                \App\Services\MysqlService::grantPrivileges($request->username, $request->name);
            }

            return $this->ok(['message' => "Database '{$request->name}' created"], 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function dbUserCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'database:manage')) return $r;

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
            'database' => 'required|string',
        ]);

        try {
            \App\Services\MysqlService::createDatabaseUser($request->username, $request->password);
            \App\Services\MysqlService::grantPrivileges($request->username, $request->database);
            return $this->ok(['message' => "User '{$request->username}' created and granted access to '{$request->database}'"], 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function dbDelete(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'database:manage')) return $r;

        $request->validate(['name' => 'required|string']);

        try {
            \App\Services\MysqlService::dropDatabase($request->name);
            return $this->ok(['message' => "Database '{$request->name}' deleted"]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function dbList(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'database:manage')) return $r;

        try {
            $databases = \App\Services\MysqlService::getDatabases();
            return $this->ok(['databases' => $databases]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // â”€â”€â”€ SSL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function sslIssue(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'ssl:manage')) return $r;

        $request->validate(['domain' => 'required|string']);

        try {
            $result = Process::run("certbot certonly --webroot -w /home/*/public_html -d {$request->domain} --non-interactive --agree-tos --register-unsafely-without-email 2>&1");
            if ($result->failed()) {
                return $this->fail('SSL issuance failed: ' . $result->errorOutput());
            }
            return $this->ok(['message' => "SSL issued for {$request->domain}"]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function sslRenew(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'ssl:manage')) return $r;

        $request->validate(['domain' => 'required|string']);

        try {
            $result = Process::run("certbot renew --cert-name {$request->domain} 2>&1");
            return $this->ok(['message' => "Renewal attempted for {$request->domain}", 'output' => $result->output()]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function sslStatus(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'ssl:manage')) return $r;

        $domain = $request->domain;
        if (!$domain) return $this->fail('domain required');

        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        if (!file_exists($certPath)) {
            return $this->ok(['domain' => $domain, 'has_ssl' => false]);
        }

        $cert = openssl_x509_parse(file_get_contents($certPath));
        return $this->ok([
            'domain' => $domain,
            'has_ssl' => true,
            'issuer' => $cert['issuer']['O'] ?? 'Unknown',
            'valid_from' => date('Y-m-d', $cert['validFrom_time_t']),
            'valid_to' => date('Y-m-d', $cert['validTo_time_t']),
        ]);
    }

    // â”€â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function findSite(int $id, string $domain): ?WordPressSite
    {
        if ($id > 0) return WordPressSite::find($id);
        if ($domain) return WordPressSite::where('domain', $domain)->first();
        return null;
    }

    private function resellerGuard(Request $request, string $username): ?JsonResponse
    {
        $token = $this->token($request);
        if (!$token->isReseller()) return null;

        $user = (new AccountService())->getUser($username);
        if (!$user) return null; // let the caller handle not-found

        // Reseller can only manage accounts they created (check reseller field or ownership)
        $resellerOf = $user['reseller'] ?? null;
        if ($resellerOf && $resellerOf !== $token->reseller_username) {
            return $this->fail('Cannot manage account belonging to another reseller', 403);
        }

        return null;
    }
}
