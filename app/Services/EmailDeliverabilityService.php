<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class EmailDeliverabilityService
{
    protected string $opendkimConf = '/etc/opendkim.conf';
    protected string $opendkimDir = '/etc/opendkim';
    protected string $keyDir = '/etc/opendkim/keys';
    protected string $keyTable = '/etc/opendkim/KeyTable';
    protected string $signingTable = '/etc/opendkim/SigningTable';
    protected string $trustedHosts = '/etc/opendkim/TrustedHosts';
    protected string $postfixMain = '/etc/postfix/main.cf';
    protected string $zoneDir = '/var/named';
    protected string $zoneMarkerStart = '; BEGIN OPENPANEL MAIL DELIVERABILITY';
    protected string $zoneMarkerEnd = '; END OPENPANEL MAIL DELIVERABILITY';

    public function enableDkim(string $domain, string $selector = 'default', bool $installDns = false): array
    {
        $domain = $this->normalizeDomain($domain);
        $selector = $this->normalizeSelector($selector);
        $account = $this->managedDomain($domain);

        $this->ensureOpenDkimInstalled();
        $this->configureOpenDkim();
        $key = $this->ensureDomainKey($domain, $selector);
        $this->configureDomainTables($domain, $selector, $key['private_key_path']);
        $this->configurePostfixMilter();
        $this->validateAndRestart();

        $records = $this->dnsHelperRecords($domain, $selector);
        $dnsApplied = false;
        if ($installDns) {
            $dnsApplied = $this->installDnsHelperRecords($domain, $selector)['applied'];
        }

        $this->recordDomainState($domain, $selector, $account?->id, $key, $records, 'enabled', null);

        return [
            'success' => true,
            'domain' => $domain,
            'selector' => $selector,
            'provider' => 'opendkim',
            'dkim_enabled' => true,
            'dns_applied' => $dnsApplied,
            'records' => $records,
            'warning' => 'External deliverability still depends on public DNS, rDNS, IP reputation, SPF, DKIM, and DMARC.',
        ];
    }

    public function status(string $domain, string $selector = 'default'): array
    {
        $domain = $this->normalizeDomain($domain);
        $selector = $this->normalizeSelector($selector);
        $keyPath = $this->privateKeyPath($domain, $selector);
        $publicPath = $this->publicKeyPath($domain, $selector);
        $records = $this->dnsHelperRecords($domain, $selector);
        $metadata = DB::table('email_deliverability_domains')->where('domain', $domain)->first();

        return [
            'success' => true,
            'domain' => $domain,
            'selector' => $selector,
            'opendkim_installed' => $this->commandExists('opendkim'),
            'opendkim_active' => $this->serviceActive('opendkim'),
            'postfix_milter_configured' => $this->postfixMilterConfigured(),
            'key_exists' => $this->rootFileExists($keyPath),
            'public_record_exists' => $this->rootFileExists($publicPath),
            'key_table' => $this->fileContains($this->keyTable, "._domainkey.{$domain}"),
            'signing_table' => $this->fileContains($this->signingTable, "*@{$domain}"),
            'trusted_hosts' => $this->fileContains($this->trustedHosts, $domain),
            'dns_zone_exists' => $this->rootFileExists($this->zoneFile($domain)),
            'dns_helper_installed' => $this->zoneHasOpenPanelRecords($domain),
            'records' => $records,
            'last_status' => $metadata->last_status ?? null,
            'last_error' => $metadata->last_error ?? null,
            'updated_at' => $metadata->updated_at ?? null,
        ];
    }

    public function dnsHelperRecords(string $domain, string $selector = 'default'): array
    {
        $domain = $this->normalizeDomain($domain);
        $selector = $this->normalizeSelector($selector);
        $hostname = $this->mailHostname($domain);
        $serverIp = $this->serverIp();
        $dkimValue = $this->publicDkimValue($domain, $selector);

        return [
            'mx' => [
                'name' => '@',
                'type' => 'MX',
                'priority' => 10,
                'value' => "{$hostname}.",
                'ttl' => 3600,
            ],
            'spf' => [
                'name' => '@',
                'type' => 'TXT',
                'value' => "v=spf1 mx a ip4:{$serverIp} -all",
                'ttl' => 3600,
            ],
            'dmarc' => [
                'name' => '_dmarc',
                'type' => 'TXT',
                'value' => "v=DMARC1; p=quarantine; rua=mailto:admin@{$domain}",
                'ttl' => 3600,
            ],
            'dkim' => [
                'name' => "{$selector}._domainkey",
                'type' => 'TXT',
                'value' => $dkimValue ?: null,
                'ttl' => 3600,
            ],
        ];
    }

    public function installDnsHelperRecords(string $domain, string $selector = 'default'): array
    {
        $domain = $this->normalizeDomain($domain);
        $selector = $this->normalizeSelector($selector);
        $zoneFile = $this->zoneFile($domain);

        if (!$this->rootFileExists($zoneFile)) {
            return [
                'success' => false,
                'applied' => false,
                'domain' => $domain,
                'message' => 'DNS zone file not found; return helper records only.',
                'records' => $this->dnsHelperRecords($domain, $selector),
            ];
        }

        $content = $this->readRootFile($zoneFile);
        $content = $this->removeManagedZoneBlock($content);
        $content = rtrim($content) . "\n\n" . $this->managedZoneBlock($domain, $selector) . "\n";

        $tmp = tempnam(sys_get_temp_dir(), 'opdns-');
        file_put_contents($tmp, $content);
        $check = Process::run('sudo named-checkzone ' . escapeshellarg($domain) . ' ' . escapeshellarg($tmp) . ' 2>&1');
        if ($check->failed()) {
            @unlink($tmp);
            throw new RuntimeException('named-checkzone failed: ' . $this->safeOutput($check->output() ?: $check->errorOutput()));
        }

        Process::run('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($zoneFile));
        @unlink($tmp);
        Process::run('sudo chown named:named ' . escapeshellarg($zoneFile) . ' 2>/dev/null || true');
        Process::run('sudo chmod 640 ' . escapeshellarg($zoneFile) . ' 2>/dev/null || true');
        Process::run('sudo rndc reload ' . escapeshellarg($domain) . ' >/dev/null 2>&1 || sudo systemctl reload named >/dev/null 2>&1');

        return [
            'success' => true,
            'applied' => true,
            'domain' => $domain,
            'records' => $this->dnsHelperRecords($domain, $selector),
        ];
    }

    public function testSigning(string $fromEmail, string $toEmail): array
    {
        $fromEmail = strtolower(trim($fromEmail));
        $toEmail = strtolower(trim($toEmail));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.');
        }

        $toMailbox = DB::table('email_accounts')->where('email', $toEmail)->whereNull('deleted_at')->first();
        if (!$toMailbox || empty($toMailbox->mailbox_path)) {
            throw new RuntimeException('Recipient mailbox not found.');
        }

        $before = $this->maildirFiles($toMailbox->mailbox_path);
        $subject = 'OpenPanel DKIM validation ' . date('YmdHis');
        $message = "From: {$fromEmail}\nTo: {$toEmail}\nSubject: {$subject}\n\nOpenPanel DKIM validation message.\n";
        $tmp = tempnam(sys_get_temp_dir(), 'opdkim-msg-');
        file_put_contents($tmp, $message);

        $send = Process::timeout(30)->run('sudo sendmail -f ' . escapeshellarg($fromEmail) . ' ' . escapeshellarg($toEmail) . ' < ' . escapeshellarg($tmp) . ' 2>&1');
        @unlink($tmp);
        sleep(2);

        $after = $this->maildirFiles($toMailbox->mailbox_path);
        $newFiles = array_values(array_diff($after, $before));
        $signed = false;
        foreach ($newFiles as $file) {
            $raw = $this->readRootFile($file);
            if (stripos($raw, 'DKIM-Signature:') !== false) {
                $signed = true;
                break;
            }
        }

        return [
            'success' => $send->successful() && $signed,
            'from' => $fromEmail,
            'to' => $toEmail,
            'message_delivered' => count($newFiles) > 0,
            'dkim_signature_present' => $signed,
        ];
    }

    protected function ensureOpenDkimInstalled(): void
    {
        if ($this->commandExists('opendkim') && $this->commandExists('opendkim-genkey')) {
            return;
        }

        Process::timeout(300)->run('sudo dnf -y install epel-release >/dev/null 2>&1 || true');
        $install = Process::timeout(300)->run('sudo dnf -y install opendkim opendkim-tools 2>&1 || sudo dnf -y install opendkim 2>&1');
        if ($install->failed() || !$this->commandExists('opendkim')) {
            throw new RuntimeException('OpenDKIM install failed: ' . $this->safeOutput($install->output() ?: $install->errorOutput()));
        }
    }

    protected function configureOpenDkim(): void
    {
        Process::run('sudo mkdir -p ' . escapeshellarg($this->opendkimDir) . ' ' . escapeshellarg($this->keyDir));
        Process::run('sudo touch ' . escapeshellarg($this->keyTable) . ' ' . escapeshellarg($this->signingTable) . ' ' . escapeshellarg($this->trustedHosts));

        $trusted = $this->mergeUniqueLines($this->readRootFile($this->trustedHosts), [
            '127.0.0.1',
            '::1',
            'localhost',
            $this->serverHostname(),
        ]);
        $this->writeRootFile($this->trustedHosts, $trusted, '0644', 'opendkim:opendkim');
        $this->writeRootFile($this->keyTable, $this->readRootFile($this->keyTable), '0644', 'opendkim:opendkim');
        $this->writeRootFile($this->signingTable, $this->readRootFile($this->signingTable), '0644', 'opendkim:opendkim');

        $config = <<<'CONF'
Syslog                  yes
SyslogSuccess           yes
Canonicalization        relaxed/simple
Mode                    sv
SubDomains              no
OversignHeaders         From
UserID                  opendkim:opendkim
Socket                  inet:8891@localhost
PidFile                 /run/opendkim/opendkim.pid
UMask                   002
KeyTable                refile:/etc/opendkim/KeyTable
SigningTable            refile:/etc/opendkim/SigningTable
ExternalIgnoreList      refile:/etc/opendkim/TrustedHosts
InternalHosts           refile:/etc/opendkim/TrustedHosts
CONF;

        $this->writeRootFile($this->opendkimConf, $config . "\n", '0644', 'root:root');
        Process::run('sudo chown -R opendkim:opendkim ' . escapeshellarg($this->opendkimDir));
        Process::run('sudo chmod 0755 ' . escapeshellarg($this->opendkimDir));
        Process::run('sudo chmod 0750 ' . escapeshellarg($this->keyDir));
    }

    protected function ensureDomainKey(string $domain, string $selector): array
    {
        $dir = "{$this->keyDir}/{$domain}";
        $private = $this->privateKeyPath($domain, $selector);
        $public = $this->publicKeyPath($domain, $selector);

        Process::run('sudo mkdir -p ' . escapeshellarg($dir));
        if (!$this->rootFileExists($private) || !$this->rootFileExists($public)) {
            $generate = Process::timeout(60)->run(
                'sudo opendkim-genkey -b 2048 -D ' . escapeshellarg($dir)
                . ' -d ' . escapeshellarg($domain)
                . ' -s ' . escapeshellarg($selector)
                . ' 2>&1'
            );
            if ($generate->failed()) {
                throw new RuntimeException('DKIM key generation failed: ' . $this->safeOutput($generate->output() ?: $generate->errorOutput()));
            }
        }

        Process::run('sudo chown -R opendkim:opendkim ' . escapeshellarg($dir));
        Process::run('sudo chmod 0750 ' . escapeshellarg($dir));
        Process::run('sudo chmod 0640 ' . escapeshellarg($private));
        Process::run('sudo chmod 0644 ' . escapeshellarg($public));

        return [
            'private_key_path' => $private,
            'public_key_path' => $public,
            'public_record' => $this->publicDkimValue($domain, $selector),
        ];
    }

    protected function configureDomainTables(string $domain, string $selector, string $privateKeyPath): void
    {
        $keyName = "{$selector}._domainkey.{$domain}";
        $keyLine = "{$keyName} {$domain}:{$selector}:{$privateKeyPath}";
        $signingLine = "*@{$domain} {$keyName}";

        $keyTable = $this->upsertLine($this->readRootFile($this->keyTable), $keyName, $keyLine);
        $signingTable = $this->upsertLine($this->readRootFile($this->signingTable), "*@{$domain}", $signingLine);
        $trustedHosts = $this->mergeUniqueLines($this->readRootFile($this->trustedHosts), [$domain, ".{$domain}"]);

        $this->writeRootFile($this->keyTable, $keyTable, '0644', 'opendkim:opendkim');
        $this->writeRootFile($this->signingTable, $signingTable, '0644', 'opendkim:opendkim');
        $this->writeRootFile($this->trustedHosts, $trustedHosts, '0644', 'opendkim:opendkim');
    }

    protected function configurePostfixMilter(): void
    {
        $settings = [
            'milter_default_action' => 'accept',
            'milter_protocol' => '6',
            'smtpd_milters' => 'inet:127.0.0.1:8891',
            'non_smtpd_milters' => 'inet:127.0.0.1:8891',
        ];

        foreach ($settings as $key => $value) {
            Process::run('sudo postconf -e ' . escapeshellarg("{$key} = {$value}") . ' 2>&1');
        }
    }

    protected function validateAndRestart(): void
    {
        $postfix = Process::run('sudo postfix check 2>&1');
        if ($postfix->failed()) {
            throw new RuntimeException('Postfix validation failed: ' . $this->safeOutput($postfix->output() ?: $postfix->errorOutput()));
        }

        Process::run('sudo systemctl enable opendkim >/dev/null 2>&1');
        $restartDkim = Process::run('sudo systemctl restart opendkim 2>&1');
        if ($restartDkim->failed()) {
            throw new RuntimeException('OpenDKIM restart failed: ' . $this->safeOutput($restartDkim->output() ?: $restartDkim->errorOutput()));
        }

        $restartPostfix = Process::run('sudo systemctl restart postfix 2>&1');
        if ($restartPostfix->failed()) {
            throw new RuntimeException('Postfix restart failed: ' . $this->safeOutput($restartPostfix->output() ?: $restartPostfix->errorOutput()));
        }
    }

    protected function recordDomainState(string $domain, string $selector, ?int $accountId, array $key, array $records, string $status, ?string $error): void
    {
        DB::table('email_deliverability_domains')->updateOrInsert(
            ['domain' => $domain, 'selector' => $selector],
            [
                'account_id' => $accountId,
                'dkim_enabled' => $status === 'enabled',
                'dkim_public_record' => $key['public_record'] ?? null,
                'dkim_private_key_path' => $key['private_key_path'] ?? null,
                'spf_record' => $records['spf']['value'] ?? null,
                'dmarc_record' => $records['dmarc']['value'] ?? null,
                'mx_record' => ($records['mx']['priority'] ?? 10) . ' ' . ($records['mx']['value'] ?? ''),
                'last_status' => $status,
                'last_error' => $error,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    protected function managedZoneBlock(string $domain, string $selector): string
    {
        $records = $this->dnsHelperRecords($domain, $selector);
        $lines = [
            $this->zoneMarkerStart,
            '@ 3600 IN MX ' . $records['mx']['priority'] . ' ' . $records['mx']['value'],
            $this->txtRecordLine('@', $records['spf']['value']),
            $this->txtRecordLine('_dmarc', $records['dmarc']['value']),
        ];

        if (!empty($records['dkim']['value'])) {
            $lines[] = $this->txtRecordLine($records['dkim']['name'], $records['dkim']['value']);
        }

        $lines[] = $this->zoneMarkerEnd;
        return implode("\n", $lines);
    }

    protected function txtRecordLine(string $name, string $value): string
    {
        $chunks = str_split($value, 240);
        $quoted = implode(' ', array_map(fn($chunk) => '"' . addcslashes($chunk, "\\\"") . '"', $chunks));
        return "{$name} 3600 IN TXT {$quoted}";
    }

    protected function removeManagedZoneBlock(string $content): string
    {
        $pattern = '/' . preg_quote($this->zoneMarkerStart, '/') . '.*?' . preg_quote($this->zoneMarkerEnd, '/') . '\s*/s';
        return preg_replace($pattern, '', $content) ?? $content;
    }

    protected function zoneHasOpenPanelRecords(string $domain): bool
    {
        $zoneFile = $this->zoneFile($domain);
        return $this->rootFileExists($zoneFile) && str_contains($this->readRootFile($zoneFile), $this->zoneMarkerStart);
    }

    protected function publicDkimValue(string $domain, string $selector): ?string
    {
        $public = $this->readRootFile($this->publicKeyPath($domain, $selector));
        if ($public === '') {
            return null;
        }
        preg_match_all('/"([^"]+)"/', $public, $matches);
        if (empty($matches[1])) {
            return null;
        }
        return implode('', $matches[1]);
    }

    protected function managedDomain(string $domain): ?object
    {
        $account = DB::table('accounts')->where('domain', $domain)->first();
        if ($account) {
            return $account;
        }

        if (DB::table('email_domains')->where('domain', $domain)->exists()) {
            return null;
        }

        if (DB::table('dns_zones')->where('domain', $domain)->exists()) {
            return null;
        }

        throw new RuntimeException("Domain is not managed by OpenPanel: {$domain}");
    }

    protected function postfixMilterConfigured(): bool
    {
        $main = $this->readRootFile($this->postfixMain);
        return str_contains($main, 'smtpd_milters = inet:127.0.0.1:8891')
            && str_contains($main, 'non_smtpd_milters = inet:127.0.0.1:8891');
    }

    protected function upsertLine(string $content, string $needle, string $line): string
    {
        $lines = array_values(array_filter(explode("\n", $content), fn($existing) => trim($existing) !== '' && !str_starts_with(trim($existing), $needle . ' ')));
        $lines[] = $line;
        return implode("\n", $lines) . "\n";
    }

    protected function mergeUniqueLines(string $content, array $newLines): string
    {
        $lines = [];
        foreach (array_merge(explode("\n", $content), $newLines) as $line) {
            $line = trim($line);
            if ($line === '' || in_array($line, $lines, true)) {
                continue;
            }
            $lines[] = $line;
        }
        return implode("\n", $lines) . "\n";
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain, ". \t\n\r\0\x0B"));
        if (!preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new RuntimeException('Invalid domain.');
        }
        return $domain;
    }

    protected function normalizeSelector(string $selector): string
    {
        $selector = strtolower(trim($selector));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $selector)) {
            throw new RuntimeException('Invalid DKIM selector.');
        }
        return $selector;
    }

    protected function privateKeyPath(string $domain, string $selector): string
    {
        return "{$this->keyDir}/{$domain}/{$selector}.private";
    }

    protected function publicKeyPath(string $domain, string $selector): string
    {
        return "{$this->keyDir}/{$domain}/{$selector}.txt";
    }

    protected function zoneFile(string $domain): string
    {
        return "{$this->zoneDir}/{$domain}.db";
    }

    protected function mailHostname(string $domain): string
    {
        return "mail.{$domain}";
    }

    protected function serverIp(): string
    {
        $ip = trim(Process::run("hostname -I | awk '{print $1}'")->output());
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '192.0.2.10';
    }

    protected function serverHostname(): string
    {
        $hostname = trim(Process::run('hostname -f 2>/dev/null || hostname')->output());
        return $hostname ?: 'localhost';
    }

    protected function commandExists(string $command): bool
    {
        return Process::run('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1')->successful()
            || Process::run('test -x ' . escapeshellarg("/usr/sbin/{$command}") . ' -o -x ' . escapeshellarg("/usr/bin/{$command}"))->successful()
            || Process::run('sudo test -x ' . escapeshellarg("/usr/sbin/{$command}") . ' -o -x ' . escapeshellarg("/usr/bin/{$command}"))->successful();
    }

    protected function serviceActive(string $service): bool
    {
        return trim(Process::run('systemctl is-active ' . escapeshellarg($service) . ' 2>/dev/null')->output()) === 'active';
    }

    protected function rootFileExists(string $path): bool
    {
        return Process::run('sudo test -f ' . escapeshellarg($path))->successful();
    }

    protected function fileContains(string $path, string $needle): bool
    {
        if (!$this->rootFileExists($path)) {
            return false;
        }
        return str_contains($this->readRootFile($path), $needle);
    }

    protected function writeRootFile(string $path, string $content, string $mode = '0644', string $owner = 'root:root'): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'opfile-');
        file_put_contents($tmp, $content);
        Process::run('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path));
        Process::run('sudo chown ' . escapeshellarg($owner) . ' ' . escapeshellarg($path) . ' 2>/dev/null || true');
        Process::run('sudo chmod ' . escapeshellarg($mode) . ' ' . escapeshellarg($path) . ' 2>/dev/null || true');
        @unlink($tmp);
    }

    protected function readRootFile(string $path): string
    {
        $result = Process::run('sudo cat ' . escapeshellarg($path) . ' 2>/dev/null');
        return $result->successful() ? $result->output() : '';
    }

    protected function maildirFiles(string $mailboxPath): array
    {
        $result = Process::run('sudo find ' . escapeshellarg($mailboxPath . '/new') . ' ' . escapeshellarg($mailboxPath . '/cur') . ' -type f 2>/dev/null | sort');
        return array_values(array_filter(explode("\n", trim($result->output()))));
    }

    protected function safeOutput(string $output): string
    {
        $output = preg_replace('/\b(pass(word)?|secret|token|key)=\S+/i', '$1=[redacted]', $output);
        $output = preg_replace('/-----BEGIN .*?-----.*?-----END .*?-----/s', '[private-key-redacted]', $output);
        return mb_substr(trim(preg_replace('/\s+/', ' ', $output)) ?: 'unknown error', 0, 500);
    }
}
