<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSslController extends Controller
{
    protected function username(): string
    {
        return auth()->user()->username;
    }

    public function index()
    {
        $username = $this->username();
        $domains = DB::connection('openpanel')->table('domains')
            ->where('user', $username)
            ->get();

        $certs = [];
        foreach ($domains as $domain) {
            $certFile = "/etc/letsencrypt/live/{$domain->domain}/fullchain.pem";
            $certs[$domain->domain] = [
                'exists' => file_exists($certFile),
                'expires' => file_exists($certFile) ? $this->getCertExpiry($certFile) : null,
            ];
        }

        return view('user-panel.ssl.index', compact('domains', 'certs'));
    }

    public function generate()
    {
        $username = $this->username();
        $domains = DB::connection('openpanel')->table('domains')
            ->where('user', $username)
            ->pluck('domain');

        return view('user-panel.ssl.generate', compact('domains'));
    }

    public function requestCert(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        $username = $this->username();

        $owned = DB::connection('openpanel')->table('domains')
            ->where('user', $username)
            ->where('domain', $request->domain)
            ->exists();

        if (!$owned) {
            return back()->with('error', 'Domain not owned by you.');
        }

        $domain = $request->domain;
        $docRoot = "/home/{$username}/web/{$domain}/public_html";

        $result = ShellService::exec("certbot certonly --webroot -w " . escapeshellarg($docRoot) . " -d " . escapeshellarg($domain) . " --non-interactive --agree-tos --email admin@{$domain} 2>&1");

        if (str_contains($result, 'Successfully')) {
            return back()->with('success', "SSL certificate generated for {$domain}.");
        }

        return back()->with('error', "SSL generation failed: " . substr($result, 0, 500));
    }

    public function selfSigned(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        $username = $this->username();

        $owned = DB::connection('openpanel')->table('domains')
            ->where('user', $username)
            ->where('domain', $request->domain)
            ->exists();

        if (!$owned) {
            return back()->with('error', 'Domain not owned by you.');
        }

        $domain = $request->domain;
        $sslDir = "/home/{$username}/ssl";

        ShellService::exec("mkdir -p " . escapeshellarg($sslDir));

        ShellService::exec("openssl req -x509 -nodes -days 365 -newkey rsa:2048 " .
            "-keyout " . escapeshellarg("{$sslDir}/{$domain}.key") . " " .
            "-out " . escapeshellarg("{$sslDir}/{$domain}.crt") . " " .
            "-subj '/CN={$domain}/O=OpenPanel/C=US' 2>&1");

        return back()->with('success', "Self-signed SSL generated for {$domain}.");
    }

    private function getCertExpiry(string $certFile): ?string
    {
        $output = ShellService::exec("openssl x509 -enddate -noout -in " . escapeshellarg($certFile) . " 2>/dev/null");
        if (preg_match('/notAfter=(.+)$/', $output, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
