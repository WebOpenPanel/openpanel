<?php

namespace App\Http\Controllers;

use App\Models\SslCertificate;
use App\Models\UserAccount;
use App\Services\WebServerService;
use App\Services\ShellService;
use Illuminate\Http\Request;

class SslController extends Controller
{
    public function index(Request $request)
    {
        $query = SslCertificate::with('userAccount');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $certificates = $query->latest()->paginate(20);
        return view('ssl.index', compact('certificates'));
    }

    public function generate()
    {
        $accounts = UserAccount::where('suspended', 'no')->orderBy('domain')->get();
        return view('ssl.generate', compact('accounts'));
    }

    public function generateSelfSigned(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'user_account_id' => 'nullable|exists:user_accounts,id',
        ]);
        $domain = $request->domain;
        $cert = $this->createSelfSignedCert($domain);

        $certRecord = SslCertificate::create([
            'user_account_id' => $request->user_account_id,
            'domain' => $domain,
            'certificate' => $cert['cert'],
            'private_key' => $cert['key'],
            'type' => 'self_signed',
            'status' => 'active',
            'issued_at' => now(),
            'expires_at' => now()->addYears(1),
        ]);

        $this->writeSslToDisk($domain, $cert['cert'], $cert['key']);

        return back()->with('success', "Self-signed SSL for '{$domain}' generated.");
    }

    public function install(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'certificate' => 'required|string',
            'private_key' => 'required|string',
            'ca_bundle' => 'nullable|string',
            'user_account_id' => 'nullable|exists:user_accounts,id',
        ]);

        $domain = $request->domain;
        $certContent = $request->certificate;
        $keyContent = $request->private_key;

        SslCertificate::create([
            'user_account_id' => $request->user_account_id,
            'domain' => $domain,
            'certificate' => $certContent,
            'private_key' => $keyContent,
            'ca_bundle' => $request->ca_bundle,
            'type' => 'manual',
            'status' => 'active',
            'issued_at' => now(),
        ]);

        $this->writeSslToDisk($domain, $certContent, $keyContent, $request->ca_bundle);

        return back()->with('success', "SSL certificate for '{$domain}' installed.");
    }

    public function destroy(SslCertificate $certificate)
    {
        $domain = $certificate->domain;
        $this->removeSslFromDisk($domain);
        $certificate->delete();
        return back()->with('success', "SSL certificate for '{$domain}' deleted.");
    }

    public function letsEncrypt()
    {
        $accounts = UserAccount::where('suspended', 'no')->orderBy('domain')->get();
        return view('ssl.letsencrypt', compact('accounts'));
    }

    public function letsEncryptIssue(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'email' => 'nullable|email',
        ]);
        $output = WebServerService::letsEncryptIssue($request->domain, $request->email ?? '');
        return back()->with('output', $output)->with('success', "Let's Encrypt certificate issued for {$request->domain}.");
    }

    public function letsEncryptRenew(Request $request)
    {
        $request->validate(['domain' => 'nullable|string|max:255']);
        $output = WebServerService::letsEncryptRenew($request->domain ?? '');
        return back()->with('output', $output)->with('success', 'Certificate renewal initiated.');
    }

    // SAN management (ported from ajax_ssl_certificate.php)
    public function addSan(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'san_type' => 'required|in:mail,webmail,ftp,cpanel',
        ]);
        $sanDomain = $request->san_type . '.' . $request->domain;
        $output = WebServerService::autoSslAddSan($request->domain, $sanDomain);
        return back()->with('output', $output)->with('success', "SAN '{$sanDomain}' added.");
    }

    // CSR generator (ported from ajax_ssl_certificate.php)
    public function generateCsr(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'country' => 'required|string|max:2',
            'state' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'organization' => 'required|string|max:100',
            'email' => 'nullable|email',
        ]);

        $dn = [
            'countryName' => $request->country,
            'stateOrProvinceName' => $request->state,
            'localityName' => $request->city,
            'organizationName' => $request->organization,
            'commonName' => $request->domain,
        ];
        if ($request->filled('email')) {
            $dn['emailAddress'] = $request->email;
        }

        $privKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new($dn, $privKey);
        openssl_csr_export($csr, $csrOut);
        openssl_pkey_export($privKey, $keyOut);

        return back()->with('csr', $csrOut)->with('key', $keyOut)->with('success', 'CSR generated.');
    }

    // DNS validation (ported from ajax_ssl_certificate.php)
    public function validateDomainDns(Request $request)
    {
        $request->validate(['domain' => 'required|string|max:255']);
        $domain = $request->domain;
        $serverIp = trim(ShellService::exec("hostname -I 2>/dev/null | awk '{print \$1}'"));
        $dnsIp = trim(ShellService::exec("dig +short {$domain} @8.8.8.8 2>/dev/null"));
        $matches = !empty($dnsIp) && $dnsIp === $serverIp;
        return back()->with('dns_matches', $matches)->with('dns_ip', $dnsIp)->with('server_ip', $serverIp);
    }

    // Force renew all domains
    public function forceRenewAll()
    {
        $output = WebServerService::letsEncryptRenew();
        return back()->with('output', $output)->with('success', 'All SSL certificates renewed.');
    }

    // Get SSL info for domain
    public function getInfo(string $domain)
    {
        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        $info = null;
        if (file_exists($certPath)) {
            $certContent = file_get_contents($certPath);
            $certData = openssl_x509_parse($certContent);
            if ($certData) {
                $info = [
                    'subject' => $certData['subject']['CN'] ?? '',
                    'issuer' => $certData['issuer']['O'] ?? '',
                    'valid_from' => date('Y-m-d H:i:s', $certData['validFrom_time_t'] ?? 0),
                    'valid_to' => date('Y-m-d H:i:s', $certData['validTo_time_t'] ?? 0),
                    'serial' => $certData['serialNumberHex'] ?? '',
                    'san' => $certData['extensions']['subjectAltName'] ?? '',
                ];
            }
        }
        $dbCert = SslCertificate::where('domain', $domain)->latest()->first();
        return view('ssl.info', compact('domain', 'info', 'dbCert'));
    }

    private function createSelfSignedCert(string $domain): array
    {
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'California',
            'localityName' => 'San Francisco',
            'organizationName' => 'OpenPanel',
            'commonName' => $domain,
        ];
        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new($dn, $privKey);
        $cert = openssl_csr_sign($csr, null, $privKey, 365);
        openssl_x509_export($cert, $certOut);
        openssl_pkey_export($privKey, $keyOut);
        return ['cert' => $certOut, 'key' => $keyOut];
    }

    private function writeSslToDisk(string $domain, string $cert, string $key, ?string $caBundle = null): void
    {
        $certDir = "/etc/pki/tls/certs/{$domain}";
        $keyDir = "/etc/pki/tls/private/{$domain}";
        if (!is_dir($certDir)) @mkdir($certDir, 0755, true);
        if (!is_dir($keyDir)) @mkdir($keyDir, 0700, true);
        ShellService::writeFile("{$certDir}/{$domain}.crt", $cert);
        ShellService::writeFile("{$keyDir}/{$domain}.key", $key);
        if ($caBundle) {
            ShellService::writeFile("{$certDir}/ca-bundle.crt", $caBundle);
        }
    }

    private function removeSslFromDisk(string $domain): void
    {
        $files = [
            "/etc/pki/tls/certs/{$domain}/{$domain}.crt",
            "/etc/pki/tls/certs/{$domain}/ca-bundle.crt",
            "/etc/pki/tls/private/{$domain}/{$domain}.key",
            "/etc/letsencrypt/live/{$domain}/fullchain.pem",
            "/etc/letsencrypt/live/{$domain}/privkey.pem",
        ];
        foreach ($files as $f) {
            if (file_exists($f)) @unlink($f);
        }
    }
}
