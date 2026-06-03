<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\DomainAlias;
use App\Services\ShellService;
use App\Services\DnsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDomainController extends Controller
{
    protected function username(): string
    {
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    protected function accountId(): ?int
    {
        $account = DB::table('accounts')->where('username', $this->username())->first();
        return $account?->id;
    }

    public function index()
    {
        $id = $this->accountId();
        $domains = $id ? Domain::where('user_account_id', $id)->get() : collect();
        return view('user-panel.domains.index', compact('domains'));
    }

    public function subdomains()
    {
        $id = $this->accountId();
        $subdomains = $id ? Subdomain::where('user_account_id', $id)->get() : collect();
        $domains = $id ? Domain::where('user_account_id', $id)->pluck('domain') : collect();
        return view('user-panel.domains.subdomains', compact('subdomains', 'domains'));
    }

    public function aliases()
    {
        $id = $this->accountId();
        $aliases = $id ? DomainAlias::where('user_account_id', $id)->get() : collect();
        $domains = $id ? Domain::where('user_account_id', $id)->pluck('domain') : collect();
        return view('user-panel.domains.aliases', compact('aliases', 'domains'));
    }

    public function addSubdomain(Request $request)
    {
        $request->validate([
            'subdomain' => 'required|string|regex:/^[a-z0-9\-]+$/|max:63',
            'domain' => 'required|string|max:255',
        ]);

        $sub = strtolower($request->subdomain);
        $domain = strtolower($request->domain);
        $fullDomain = "{$sub}.{$domain}";
        $username = $this->username();
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $parentExists = Domain::where('user_account_id', $id)->where('domain', $domain)->exists();
        if (!$parentExists) {
            return back()->with('error', 'Parent domain not found or not owned by you.');
        }

        $exists = Subdomain::where('user_account_id', $id)
            ->where('subdomain', $sub)
            ->where('domain', $domain)
            ->exists();
        if ($exists) {
            return back()->with('error', "Subdomain {$fullDomain} already exists.");
        }

        $docRoot = "/home/{$username}/web/{$fullDomain}/public_html";
        if (str_contains($docRoot, '..') || !str_starts_with($docRoot, "/home/{$username}/")) {
            return back()->with('error', 'Invalid document root path.');
        }

        ShellService::exec("mkdir -p " . escapeshellarg($docRoot));
        ShellService::exec("chown -R " . escapeshellarg("{$username}:{$username}") . " " . escapeshellarg("/home/{$username}/web/{$fullDomain}"));

        $serverIp = trim(ShellService::exec("hostname -I 2>/dev/null | awk '{print \$1}'"));

        Subdomain::create([
            'user_account_id' => $id,
            'domain' => $domain,
            'subdomain' => $sub,
            'document_root' => $docRoot,
            'ip_address' => $serverIp,
            'status' => 'active',
        ]);

        $this->createSubdomainVhost($fullDomain, $username, $docRoot);
        $this->addDnsRecord($domain, $sub, $serverIp);

        return back()->with('success', "Subdomain {$fullDomain} created.");
    }

    public function removeSubdomain(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $subdomain = Subdomain::where('id', $request->id)->where('user_account_id', $id)->first();
        if (!$subdomain) return back()->with('error', 'Subdomain not found.');

        $this->removeSubdomainVhost($subdomain->fullDomain());
        $subdomain->delete();

        return back()->with('success', 'Subdomain removed.');
    }

    public function addAlias(Request $request)
    {
        $request->validate([
            'alias' => 'required|string|regex:/^[a-z0-9\.\-]+$/|max:255',
            'domain' => 'required|string|max:255',
        ]);

        $aliasDomain = strtolower($request->alias);
        $domain = strtolower($request->domain);
        $id = $this->accountId();
        $username = $this->username();
        if (!$id) return back()->with('error', 'Account not found.');

        $parentExists = Domain::where('user_account_id', $id)->where('domain', $domain)->exists();
        if (!$parentExists) {
            return back()->with('error', 'Target domain not found or not owned by you.');
        }

        $exists = DomainAlias::where('alias', $aliasDomain)->exists();
        if ($exists) {
            return back()->with('error', "Alias {$aliasDomain} already exists.");
        }

        $serverIp = trim(ShellService::exec("hostname -I 2>/dev/null | awk '{print \$1}'"));

        DomainAlias::create([
            'user_account_id' => $id,
            'domain' => $domain,
            'alias' => $aliasDomain,
            'ip_address' => $serverIp,
            'status' => 'active',
        ]);

        $this->createAliasVhost($aliasDomain, $domain, $username);
        $this->addDnsRecordForAlias($aliasDomain, $serverIp);

        return back()->with('success', "Alias {$aliasDomain} → {$domain} created.");
    }

    public function removeAlias(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $alias = DomainAlias::where('id', $request->id)->where('user_account_id', $id)->first();
        if (!$alias) return back()->with('error', 'Alias not found.');

        $this->removeAliasVhost($alias->alias);
        $alias->delete();

        return back()->with('success', 'Alias removed.');
    }

    protected function createSubdomainVhost(string $fullDomain, string $username, string $docRoot): void
    {
        $vhostDir = '/etc/nginx/conf.d/vhosts';
        ShellService::exec("mkdir -p " . escapeshellarg($vhostDir));

        $conf = "server {\n"
            . "    listen 80;\n"
            . "    server_name {$fullDomain};\n"
            . "    root {$docRoot};\n"
            . "    index index.php index.html;\n\n"
            . "    location / {\n"
            . "        try_files \$uri \$uri/ /index.php?\$query_string;\n"
            . "    }\n\n"
            . "    location ~ \\.php\$ {\n"
            . "        fastcgi_pass unix:/run/php-fpm/www.sock;\n"
            . "        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;\n"
            . "        include fastcgi_params;\n"
            . "    }\n\n"
            . "    location ~ /\\. { deny all; }\n"
            . "}\n";

        ShellService::writeFile("{$vhostDir}/{$fullDomain}.conf", $conf);
        ShellService::exec("nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true");
    }

    protected function removeSubdomainVhost(string $fullDomain): void
    {
        $confPath = "/etc/nginx/conf.d/vhosts/{$fullDomain}.conf";
        if (file_exists($confPath)) {
            @unlink($confPath);
            ShellService::exec("nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true");
        }
    }

    protected function createAliasVhost(string $aliasDomain, string $targetDomain, string $username): void
    {
        $targetDocRoot = "/home/{$username}/web/{$targetDomain}/public_html";
        $vhostDir = '/etc/nginx/conf.d/vhosts';
        ShellService::exec("mkdir -p " . escapeshellarg($vhostDir));

        $conf = "server {\n"
            . "    listen 80;\n"
            . "    server_name {$aliasDomain};\n"
            . "    root {$targetDocRoot};\n"
            . "    index index.php index.html;\n\n"
            . "    location / {\n"
            . "        try_files \$uri \$uri/ /index.php?\$query_string;\n"
            . "    }\n\n"
            . "    location ~ \\.php\$ {\n"
            . "        fastcgi_pass unix:/run/php-fpm/www.sock;\n"
            . "        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;\n"
            . "        include fastcgi_params;\n"
            . "    }\n\n"
            . "    location ~ /\\. { deny all; }\n"
            . "}\n";

        ShellService::writeFile("{$vhostDir}/{$aliasDomain}.conf", $conf);
        ShellService::exec("nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true");
    }

    protected function removeAliasVhost(string $aliasDomain): void
    {
        $confPath = "/etc/nginx/conf.d/vhosts/{$aliasDomain}.conf";
        if (file_exists($confPath)) {
            @unlink($confPath);
            ShellService::exec("nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true");
        }
    }

    protected function addDnsRecord(string $domain, string $subdomain, string $ip): void
    {
        $zoneFile = DnsService::ZONE_BASE . $domain . '.db';
        if (!file_exists($zoneFile)) return;

        $record = "{$subdomain}\t14400\tIN\tA\t{$ip}\n";
        ShellService::appendFile($zoneFile, $record);
        ShellService::exec("systemctl reload named 2>/dev/null || true");
    }

    protected function addDnsRecordForAlias(string $aliasDomain, string $ip): void
    {
        $parts = explode('.', $aliasDomain);
        if (count($parts) < 2) return;

        $rootDomain = implode('.', array_slice($parts, -2));
        $sub = count($parts) > 2 ? implode('.', array_slice($parts, 0, -2)) : '@';

        $zoneFile = DnsService::ZONE_BASE . $rootDomain . '.db';
        if (!file_exists($zoneFile)) return;

        $record = "{$sub}\t14400\tIN\tA\t{$ip}\n";
        ShellService::appendFile($zoneFile, $record);
        ShellService::exec("systemctl reload named 2>/dev/null || true");
    }
}
