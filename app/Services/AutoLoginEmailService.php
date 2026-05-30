<?php

namespace App\Services;

class AutoLoginEmailService
{
    public static function generateAutoLoginToken(string $username): array
    {
        $sessionServer = md5(str_replace('op_', '', $_SERVER['REQUEST_URI'] ?? ''));
        $tokenData = json_encode([
            'panel_session' => $sessionServer,
            'username' => $username,
            'time' => time(),
        ]);
        $token = base64_encode(base64_encode($tokenData));
        return ['token' => $token, 'url' => '/roundcube/?_autologin=1&sess=' . $token];
    }

    public static function validateAutoLoginToken(string $token): ?string
    {
        $json = base64_decode(base64_decode($token));
        $data = json_decode($json);
        if (!$data || !isset($data->panel_session)) return null;
        $sessionServer = md5(str_replace('op_', '', $_SERVER['REQUEST_URI'] ?? ''));
        if ($data->panel_session !== $sessionServer) return null;
        return $data->username ?? null;
    }

    public static function getWebmailUrl(): string
    {
        $hostName = trim(ShellService::exec('hostname -f 2>/dev/null'));
        $sharedIp = '';
        $settings = \Illuminate\Support\Facades\DB::table('settings')->where('id', 1)->first();
        if ($settings) $sharedIp = $settings->shared_ip ?? '';

        $hasSsl = false;
        if ($hostName) {
            $dnsIp = trim(ShellService::exec("dig {$hostName} @8.8.8.8 +short"));
            $serverIps = explode(' ', trim(ShellService::exec('hostname -I')));
            if (in_array($dnsIp, $serverIps) && !empty($dnsIp)) {
                $certPath = '/etc/pki/tls/certs/hostname.bundle';
                $keyPath = '/etc/pki/tls/private/hostname.key';
                if (file_exists($certPath) && file_exists($keyPath)) {
                    $cert = file_get_contents($certPath);
                    $key = file_get_contents($keyPath);
                    $hasSsl = openssl_x509_check_private_key($cert, $key);
                }
                if (!$hasSsl) {
                    $domainCert = "/etc/pki/tls/certs/{$hostName}.cert";
                    $domainKey = "/etc/pki/tls/private/" . str_replace(['.cert', '.pem', '.crt'], '', $hostName) . ".key";
                    if (file_exists($domainCert) && file_exists($domainKey)) {
                        $hasSsl = openssl_x509_check_private_key(file_get_contents($domainCert), file_get_contents($domainKey));
                    }
                }
            }
        }

        $useHost = $hasSsl && !empty($hostName) ? $hostName : $sharedIp;
        return 'https://' . $useHost . '/webmail/';
    }
}
