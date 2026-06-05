<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\WordPressSite;
use App\Services\AccountService;
use App\Services\ApiTokenService;
use App\Services\DnsService;
use App\Services\DomainSslService;
use App\Services\EmailDeliverabilityService;
use App\Services\EmailService;
use App\Services\MysqlService;
use App\Services\PhpMyAdminService;
use App\Services\SslService;
use App\Services\VarnishDomainService;
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
        $version = trim(file_get_contents(base_path('VERSION'))) ?? '0.1.0-beta';
        return $this->ok(['status' => 'ok', 'version' => $version]);
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
            $result = (new AccountService())->terminate($username);
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
            if (isset($result['site'])) {
                $site = method_exists($result['site'], 'toArray') ? $result['site']->toArray() : (array) $result['site'];
                unset($site['db_password_encrypted']);
                $result['site'] = $site;
            }
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
            $result = (new WordPressService())->configureVarnishRules($site, 'cache');
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
            $result = (new WordPressService())->backupSite($site, $request->type ?? 'full');
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
            $backup = \App\Models\WordPressBackup::where('wordpress_site_id', $site->id)->find($request->backup_id);
            if (!$backup) return $this->fail('Backup not found', 404);

            $result = (new WordPressService())->restoreSite($site, $backup);
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
            if (isset($result['staging_site']['db_password_encrypted'])) {
                unset($result['staging_site']['db_password_encrypted']);
            }
            return $this->ok($result, 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpStagingPush(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        $staging = \App\Models\WordPressSite::where('parent_site_id', $site->id)
            ->where('site_type', 'staging')
            ->when($request->staging_domain, fn($q) => $q->where('domain', $request->staging_domain))
            ->first();

        if (!$staging) return $this->fail('Staging site not found', 404);

        try {
            $result = (new WordPressService())->pushStagingToLive($staging, $site);
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function wpStagingDelete(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'wordpress:manage')) return $r;

        $site = $this->findSite($request->site_id ?? 0, $request->domain ?? '');
        if (!$site) return $this->fail('Site not found', 404);

        try {
            $result = (new WordPressService())->deleteStaging($site, $request->staging_domain);
            return $this->ok($result);
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
            'username' => 'nullable|string',
            'local_part' => 'nullable|string',
            'password' => 'required|string|min:8',
            'quota' => 'nullable|integer|min:0',
            'quota_mb' => 'nullable|integer|min:0',
        ]);

        try {
            $account = (new EmailService())->accountForDomain($request->domain);
            if ($guard = $this->emailAccountGuard($request, $account)) return $guard;

            $localPart = $request->local_part ?: $request->username;
            if (!$localPart) return $this->fail('local_part or username required');

            $result = (new EmailService())->createMailbox(
                $account,
                $request->domain,
                $localPart,
                $request->password,
                (int) ($request->quota_mb ?? $request->quota ?? 250)
            );

            return $this->ok($result, 201);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailDelete(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['email' => 'required|email']);

        try {
            $account = $this->accountForMailbox($request->email);
            if ($guard = $this->emailAccountGuard($request, $account)) return $guard;

            return $this->ok((new EmailService())->deleteMailbox($request->email));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailPassword(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['email' => 'required|email', 'password' => 'required|string|min:6']);

        try {
            $account = $this->accountForMailbox($request->email);
            if ($guard = $this->emailAccountGuard($request, $account)) return $guard;

            return $this->ok((new EmailService())->changeMailboxPassword($request->email, $request->password));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailList(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        try {
            $service = new EmailService();
            $account = null;
            if ($request->domain) {
                $account = $service->accountForDomain($request->domain);
                if ($guard = $this->emailAccountGuard($request, $account)) return $guard;
            }
            return $this->ok(['accounts' => $service->listMailboxes($account)]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailSuspend(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['email' => 'required|email']);

        try {
            $account = $this->accountForMailbox($request->email);
            if ($guard = $this->emailAccountGuard($request, $account)) return $guard;

            return $this->ok((new EmailService())->suspendMailbox($request->email));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailUnsuspend(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['email' => 'required|email']);

        try {
            $account = $this->accountForMailbox($request->email);
            if ($guard = $this->emailAccountGuard($request, $account)) return $guard;

            return $this->ok((new EmailService())->unsuspendMailbox($request->email));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailTestAuth(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        try {
            $account = $this->accountForMailbox($request->email);
            if ($guard = $this->emailAccountGuard($request, $account)) return $guard;

            return $this->ok((new EmailService())->testMailboxAuth($request->email, $request->password));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailTestDelivery(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate(['from' => 'required|email', 'to' => 'required|email']);

        try {
            $fromAccount = $this->accountForMailbox($request->from);
            $toAccount = $this->accountForMailbox($request->to);
            if ($guard = $this->emailAccountGuard($request, $fromAccount)) return $guard;
            if ($guard = $this->emailAccountGuard($request, $toAccount)) return $guard;

            return $this->ok((new EmailService())->testLocalDelivery($request->from, $request->to));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // â”€â”€â”€ Database â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function emailDeliverabilityStatus(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'selector' => 'nullable|string',
        ]);

        try {
            if ($guard = $this->domainGuard($request, $request->domain)) return $guard;
            return $this->ok((new EmailDeliverabilityService())->status($request->domain, $request->selector ?? 'default'));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailDkimEnable(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'selector' => 'nullable|string',
            'install_dns' => 'nullable|boolean',
        ]);

        try {
            if ($guard = $this->domainGuard($request, $request->domain)) return $guard;
            return $this->ok((new EmailDeliverabilityService())->enableDkim(
                $request->domain,
                $request->selector ?? 'default',
                $request->boolean('install_dns')
            ));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailDeliverabilityDnsHelper(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'selector' => 'nullable|string',
            'install' => 'nullable|boolean',
        ]);

        try {
            if ($guard = $this->domainGuard($request, $request->domain)) return $guard;
            $service = new EmailDeliverabilityService();
            if ($request->boolean('install')) {
                return $this->ok($service->installDnsHelperRecords($request->domain, $request->selector ?? 'default'));
            }

            return $this->ok([
                'domain' => $request->domain,
                'selector' => $request->selector ?? 'default',
                'records' => $service->dnsHelperRecords($request->domain, $request->selector ?? 'default'),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function emailDeliverabilityTestSigning(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'email:manage')) return $r;

        $request->validate([
            'from' => 'required|email',
            'to' => 'required|email',
        ]);

        try {
            $fromAccount = $this->accountForMailbox($request->from);
            $toAccount = $this->accountForMailbox($request->to);
            if ($guard = $this->emailAccountGuard($request, $fromAccount)) return $guard;
            if ($guard = $this->emailAccountGuard($request, $toAccount)) return $guard;

            return $this->ok((new EmailDeliverabilityService())->testSigning($request->from, $request->to));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function dbCreate(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'database:manage')) return $r;

        $request->validate([
            'name' => 'required|string|regex:/^[A-Za-z0-9_]{1,64}$/',
            'username' => 'nullable|string|regex:/^[A-Za-z0-9_]{1,64}$/',
            'password' => 'nullable|string|min:8',
        ]);

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
            'username' => 'required|string|regex:/^[A-Za-z0-9_]{1,64}$/',
            'password' => 'required|string|min:8',
            'database' => 'required|string|regex:/^[A-Za-z0-9_]{1,64}$/',
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

        $request->validate(['name' => 'required|string|regex:/^[A-Za-z0-9_]{1,64}$/']);

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

    public function phpMyAdminStatus(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'database:manage')) return $r;

        return $this->ok(['phpmyadmin' => PhpMyAdminService::status()]);
    }

    // â”€â”€â”€ SSL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function sslIssue(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'ssl:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'email' => 'nullable|email',
            'redirect_https' => 'nullable|boolean',
        ]);

        try {
            $result = (new DomainSslService())->issue(
                $request->domain,
                $request->email,
                $request->boolean('redirect_https')
            );
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function sslRenew(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'ssl:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'redirect_https' => 'nullable|boolean',
        ]);

        try {
            $result = (new DomainSslService())->renew(
                $request->domain,
                $request->has('redirect_https') ? $request->boolean('redirect_https') : null
            );
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function sslForceHttps(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'ssl:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'force_https' => 'required|boolean',
        ]);

        try {
            return $this->ok((new DomainSslService())->setForceHttps($request->domain, $request->boolean('force_https')));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function sslStatus(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'ssl:manage')) return $r;

        $domain = $request->domain;
        if (!$domain) return $this->fail('domain required');

        try {
            return $this->ok((new DomainSslService())->status($domain));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function varnishStatus(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'varnish:manage')) return $r;

        $domain = $request->query('domain', $request->domain);
        if (!$domain) return $this->fail('domain required');

        try {
            if ($guard = $this->domainGuard($request, $domain)) return $guard;
            return $this->ok((new VarnishDomainService())->status($domain));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function varnishMode(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'varnish:manage')) return $r;

        $request->validate([
            'domain' => 'required|string',
            'varnish_enabled' => 'nullable|boolean',
            'varnish_mode' => 'nullable|in:bypass,shield,cache',
            'static_asset_mode' => 'nullable|in:nginx_direct,varnish_cached',
            'html_ttl' => 'nullable|integer|min:0|max:86400',
            'static_ttl' => 'nullable|integer|min:60|max:31536000',
            'grace_ttl' => 'nullable|integer|min:0|max:86400',
            'purge_enabled' => 'nullable|boolean',
        ]);

        try {
            if ($guard = $this->domainGuard($request, $request->domain)) return $guard;
            return $this->ok((new VarnishDomainService())->configure($request->domain, $request->all()));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function varnishPurge(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'varnish:manage')) return $r;

        $request->validate(['domain' => 'required|string']);

        try {
            if ($guard = $this->domainGuard($request, $request->domain)) return $guard;
            return $this->ok((new VarnishDomainService())->purge($request->domain));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function varnishTest(Request $request): JsonResponse
    {
        if ($r = $this->scope($request, 'varnish:manage')) return $r;

        $request->validate(['domain' => 'required|string']);

        try {
            if ($guard = $this->domainGuard($request, $request->domain)) return $guard;
            return $this->ok(['test' => (new VarnishDomainService())->test($request->domain)]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
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

    private function accountForMailbox(string $email): object
    {
        $mailbox = DB::table('email_accounts')->where('email', strtolower(trim($email)))->whereNull('deleted_at')->first();
        if (!$mailbox) {
            throw new \RuntimeException('Mailbox not found.');
        }

        $account = DB::table('accounts')->where('id', $mailbox->account_id)->first();
        if (!$account) {
            throw new \RuntimeException('Mailbox hosting account not found.');
        }

        return $account;
    }

    private function domainGuard(Request $request, string $domain): ?JsonResponse
    {
        $token = $this->token($request);
        if (!$token->isReseller()) {
            return null;
        }

        $domain = strtolower(trim(preg_replace('#^https?://#', '', $domain), "/ \t\n\r\0\x0B."));
        $account = DB::connection('mysql')->table('accounts')->where('domain', $domain)->first();
        if (!$account) {
            return null;
        }

        if (!property_exists($account, 'reseller') || empty($account->reseller)) {
            return $this->fail('Reseller ownership is not configured for this account', 403);
        }

        if ($account->reseller !== $token->reseller_username) {
            return $this->fail('Cannot manage domain belonging to another reseller', 403);
        }

        return null;
    }

    private function emailAccountGuard(Request $request, object $account): ?JsonResponse
    {
        $token = $this->token($request);
        if (!$token->isReseller()) {
            return null;
        }

        if (!property_exists($account, 'reseller') || empty($account->reseller)) {
            return $this->fail('Reseller ownership is not configured for this account', 403);
        }

        return $account->reseller === $token->reseller_username
            ? null
            : $this->fail('Cannot manage mailbox belonging to another reseller', 403);
    }
}
