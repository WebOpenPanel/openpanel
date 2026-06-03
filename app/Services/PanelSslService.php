<?php

namespace App\Services;

class PanelSslService
{
    const CERTBOT_BIN = '/usr/bin/certbot';
    const PANEL_CERT_DIR = '/etc/letsencrypt/live';
    const NGINX_CONF = '/usr/local/openpanel/conf/openpanel-nginx.conf';
    const PANEL_CERT_PATH = '/etc/pki/tls/certs/openpanel.crt';
    const PANEL_KEY_PATH = '/etc/pki/tls/private/openpanel.key';

    public function isCertbotInstalled(): bool
    {
        return file_exists(self::CERTBOT_BIN) || !empty(ShellService::exec('which certbot 2>/dev/null'));
    }

    public function installCertbot(): array
    {
        if ($this->isCertbotInstalled()) {
            return ['success' => true, 'message' => 'certbot already installed'];
        }

        $output = ShellService::exec('dnf install -y certbot python3-certbot-nginx 2>&1', 120);

        if ($this->isCertbotInstalled()) {
            return ['success' => true, 'message' => 'certbot installed', 'output' => $output];
        }

        $output2 = ShellService::exec('pip3 install certbot certbot-nginx 2>&1', 120);
        if ($this->isCertbotInstalled()) {
            return ['success' => true, 'message' => 'certbot installed via pip', 'output' => $output2];
        }

        return ['success' => false, 'message' => 'certbot installation failed', 'output' => $output . "\n" . $output2];
    }

    public function validateDns(string $hostname): array
    {
        $serverIp = trim(ShellService::exec("hostname -I 2>/dev/null | awk '{print \$1}'"));
        $dnsIp = trim(ShellService::exec("dig +short {$hostname} @8.8.8.8 2>/dev/null"));
        $matches = !empty($dnsIp) && $dnsIp === $serverIp;

        return [
            'hostname' => $hostname,
            'server_ip' => $serverIp,
            'dns_ip' => $dnsIp,
            'matches' => $matches,
        ];
    }

    public function issuePanelCert(string $hostname, string $email = ''): array
    {
        $dns = $this->validateDns($hostname);
        if (!$dns['matches']) {
            return [
                'success' => false,
                'message' => "DNS mismatch: {$hostname} resolves to {$dns['dns_ip']}, server IP is {$dns['server_ip']}",
            ];
        }

        if (!$this->isCertbotInstalled()) {
            $install = $this->installCertbot();
            if (!$install['success']) {
                return $install;
            }
        }

        if (empty($email)) {
            $email = 'admin@' . $hostname;
        }

        $result = ShellService::exec(
            "certbot certonly --standalone --non-interactive --agree-tos"
            . " --email " . escapeshellarg($email)
            . " -d " . escapeshellarg($hostname)
            . " --http-01-port 80"
            . " 2>&1",
            120
        );

        $certDir = self::PANEL_CERT_DIR . "/{$hostname}";
        $fullchain = "{$certDir}/fullchain.pem";
        $privkey = "{$certDir}/privkey.pem";

        if (!file_exists($fullchain) || !file_exists($privkey)) {
            return [
                'success' => false,
                'message' => 'Certificate issuance failed',
                'output' => $result,
            ];
        }

        $this->installCertToPanel($hostname);

        return [
            'success' => true,
            'message' => "Certificate issued for {$hostname}",
            'output' => $result,
        ];
    }

    public function installCertToPanel(string $hostname): bool
    {
        $certDir = self::PANEL_CERT_DIR . "/{$hostname}";
        $fullchain = "{$certDir}/fullchain.pem";
        $privkey = "{$certDir}/privkey.pem";

        if (!file_exists($fullchain) || !file_exists($privkey)) {
            return false;
        }

        ShellService::exec("cp -f " . escapeshellarg($fullchain) . " " . self::PANEL_CERT_PATH);
        ShellService::exec("cp -f " . escapeshellarg($privkey) . " " . self::PANEL_KEY_PATH);
        ShellService::exec("chmod 644 " . self::PANEL_CERT_PATH);
        ShellService::exec("chmod 600 " . self::PANEL_KEY_PATH);

        $this->reloadPanelNginx();

        return true;
    }

    public function getPanelCertInfo(): array
    {
        $certPath = self::PANEL_CERT_PATH;
        $info = [
            'type' => 'self-signed',
            'subject' => 'N/A',
            'issuer' => 'N/A',
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
            'is_letsencrypt' => false,
            'serial' => '',
        ];

        if (!file_exists($certPath)) {
            return $info;
        }

        $certContent = file_get_contents($certPath);
        $certData = openssl_x509_parse($certContent);

        if (!$certData) {
            return $info;
        }

        $info['subject'] = $certData['subject']['CN'] ?? 'N/A';
        $info['issuer'] = $certData['issuer']['O'] ?? ($certData['issuer']['CN'] ?? 'N/A');
        $info['valid_from'] = date('Y-m-d H:i:s', $certData['validFrom_time_t'] ?? 0);
        $info['valid_to'] = date('Y-m-d H:i:s', $certData['validTo_time_t'] ?? 0);
        $info['serial'] = $certData['serialNumberHex'] ?? '';

        $expiryTs = $certData['validTo_time_t'] ?? 0;
        if ($expiryTs > 0) {
            $info['days_remaining'] = max(0, (int) ((new \DateTime())->setTimestamp($expiryTs)->diff(new \DateTime()))->format('%r%a'));
        }

        $issuerO = strtolower($certData['issuer']['O'] ?? '');
        if (str_contains($issuerO, "let's encrypt") || str_contains($issuerO, 'letsencrypt') || str_contains($issuerO, "isrg")) {
            $info['type'] = 'letsencrypt';
            $info['is_letsencrypt'] = true;
        } elseif (str_contains($issuerO, 'openpanel') || str_contains($issuerO, 'self')) {
            $info['type'] = 'self-signed';
        } else {
            $info['type'] = 'other';
        }

        return $info;
    }

    public function setupAutoRenewal(): array
    {
        $renewHook = '#!/bin/bash\nsystemctl reload openpanel-nginx 2>/dev/null || true\n';
        $hookPath = '/etc/letsencrypt/renewal-hooks/deploy/openpanel-nginx.sh';
        ShellService::exec("mkdir -p /etc/letsencrypt/renewal-hooks/deploy");
        ShellService::writeFile($hookPath, $renewHook);
        ShellService::exec("chmod +x " . escapeshellarg($hookPath));

        $cronLine = '0 3 * * * root certbot renew --quiet --deploy-hook "systemctl reload openpanel-nginx" 2>/dev/null';
        $cronPath = '/etc/cron.d/certbot-renew';
        ShellService::writeFile($cronPath, $cronLine . "\n");
        ShellService::exec("chmod 644 " . escapeshellarg($cronPath));

        return [
            'success' => true,
            'message' => 'Auto-renewal configured: daily at 03:00, reloads panel nginx on success',
            'hook_path' => $hookPath,
            'cron_path' => $cronPath,
        ];
    }

    public function renewNow(): array
    {
        if (!$this->isCertbotInstalled()) {
            return ['success' => false, 'message' => 'certbot not installed'];
        }

        $result = ShellService::exec('certbot renew --quiet 2>&1', 120);

        $hostname = trim(ShellService::exec('hostname -f 2>/dev/null'));
        if (!empty($hostname)) {
            $this->installCertToPanel($hostname);
        }

        return [
            'success' => true,
            'message' => 'Renewal attempted',
            'output' => $result,
        ];
    }

    public function revokeCert(string $hostname): array
    {
        if (!$this->isCertbotInstalled()) {
            return ['success' => false, 'message' => 'certbot not installed'];
        }

        $result = ShellService::exec(
            "certbot revoke --non-interactive --cert-name " . escapeshellarg($hostname) . " --delete-after-revoke 2>&1",
            60
        );

        $this->generateSelfSigned();

        return [
            'success' => true,
            'message' => "Certificate for {$hostname} revoked, self-signed restored",
            'output' => $result,
        ];
    }

    public function generateSelfSigned(): bool
    {
        ShellService::exec("mkdir -p /etc/pki/tls/certs /etc/pki/tls/private");

        ShellService::exec(
            "openssl req -x509 -nodes -days 3650 -newkey rsa:2048"
            . " -keyout " . self::PANEL_KEY_PATH
            . " -out " . self::PANEL_CERT_PATH
            . " -subj '/C=US/ST=California/L=SanFrancisco/O=OpenPanel/CN=localhost'"
            . " 2>&1"
        );

        ShellService::exec("chmod 600 " . self::PANEL_KEY_PATH);
        ShellService::exec("chmod 644 " . self::PANEL_CERT_PATH);

        $this->reloadPanelNginx();

        return file_exists(self::PANEL_CERT_PATH) && file_exists(self::PANEL_KEY_PATH);
    }

    public function reloadPanelNginx(): bool
    {
        $test = ShellService::exec('/usr/local/openpanel/sbin/nginx -t 2>&1');
        if (str_contains($test, 'successful') || str_contains($test, 'ok') || str_contains($test, 'test is successful')) {
            ShellService::exec('systemctl reload openpanel-nginx 2>&1');
            return true;
        }
        return false;
    }
}
