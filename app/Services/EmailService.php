<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class EmailService
{
    protected string $postfixDir = '/etc/postfix/openpanel';
    protected string $dovecotPasswd = '/etc/dovecot/openpanel-passwd';
    protected string $dovecotConf = '/etc/dovecot/conf.d/99-openpanel-mail.conf';

    public function createEmailDomain(object|array $account, string $domain): array
    {
        $account = (object) $account;
        $domain = $this->normalizeDomain($domain);
        if ($domain !== $account->domain) {
            throw new RuntimeException('Email domain must belong to the hosting account.');
        }

        DB::table('email_domains')->updateOrInsert(
            ['domain' => $domain],
            [
                'account_id' => $account->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->regeneratePostfixMaps();
        $this->regenerateDovecotAuth();

        return ['success' => true, 'domain' => $domain, 'status' => 'active'];
    }

    public function createMailbox(object|array $account, string $domain, string $localPart, string $password, int $quota = 250): array
    {
        $account = (object) $account;
        $domain = $this->normalizeDomain($domain);
        $localPart = $this->normalizeLocalPart($localPart);
        $email = "{$localPart}@{$domain}";

        if ($domain !== $account->domain) {
            throw new RuntimeException('Mailbox domain must belong to the hosting account.');
        }

        $emailDomain = $this->createEmailDomain($account, $domain);
        $mailboxPath = $this->mailboxPath($account->username, $domain, $localPart);
        $this->createMaildir($mailboxPath);

        EmailAccount::updateOrCreate(
            ['email' => $email],
            [
                'account_id' => $account->id,
                'domain_id' => $emailDomain['id'] ?? null,
                'user_account_id' => null,
                'domain' => $domain,
                'local_part' => $localPart,
                'password_hash' => $this->hashPassword($password),
                'quota_mb' => $quota,
                'mailbox_path' => $mailboxPath,
                'status' => 'active',
            ]
        );

        $this->regeneratePostfixMaps();
        $this->regenerateDovecotAuth();

        return [
            'success' => true,
            'email' => $email,
            'domain' => $domain,
            'local_part' => $localPart,
            'quota_mb' => $quota,
            'mailbox_path' => $mailboxPath,
            'status' => 'active',
            'imap_host' => $domain,
            'imap_port' => 143,
            'smtp_host' => $domain,
            'smtp_port' => 587,
        ];
    }

    public function changeMailboxPassword(string $email, string $password): array
    {
        $mailbox = $this->mailbox($email);
        $mailbox->update(['password_hash' => $this->hashPassword($password)]);
        $this->regenerateDovecotAuth();

        return ['success' => true, 'email' => $mailbox->email, 'message' => 'Password changed'];
    }

    public function suspendMailbox(string $email): array
    {
        $mailbox = $this->mailbox($email);
        $mailbox->update(['status' => 'suspended']);
        $this->regeneratePostfixMaps();
        $this->regenerateDovecotAuth();

        return ['success' => true, 'email' => $mailbox->email, 'status' => 'suspended'];
    }

    public function unsuspendMailbox(string $email): array
    {
        $mailbox = $this->mailbox($email);
        $mailbox->update(['status' => 'active']);
        $this->regeneratePostfixMaps();
        $this->regenerateDovecotAuth();

        return ['success' => true, 'email' => $mailbox->email, 'status' => 'active'];
    }

    public function deleteMailbox(string $email): array
    {
        $mailbox = $this->mailbox($email);
        $path = $mailbox->mailbox_path;
        $mailbox->delete();

        if ($path && str_contains($path, '/mail/')) {
            Process::run('sudo rm -rf ' . escapeshellarg(dirname($path)));
        }

        $this->regeneratePostfixMaps();
        $this->regenerateDovecotAuth();

        return ['success' => true, 'email' => $email, 'message' => 'Mailbox deleted'];
    }

    public function listMailboxes(?object $account = null): array
    {
        $query = EmailAccount::query()->orderBy('email');
        if ($account) {
            $query->where('account_id', $account->id);
        }

        return $query->get([
            'id', 'account_id', 'domain', 'local_part', 'email',
            'quota_mb', 'mailbox_path', 'status', 'created_at',
        ])->toArray();
    }

    public function regeneratePostfixMaps(): array
    {
        $this->ensureVmailUser();
        Process::run('sudo mkdir -p ' . escapeshellarg($this->postfixDir));

        $domains = DB::table('email_domains')->where('status', 'active')->orderBy('domain')->pluck('domain')->all();
        $mailboxes = $this->activeMailboxes();

        foreach ($mailboxes as $mailbox) {
            if (!in_array($mailbox->domain, $domains, true)) {
                $domains[] = $mailbox->domain;
            }
        }
        sort($domains);

        $domainContent = implode('', array_map(fn($domain) => "{$domain} OK\n", $domains));
        $mailboxContent = '';
        foreach ($mailboxes as $mailbox) {
            $mailboxContent .= "{$mailbox->email} " . ltrim($mailbox->mailbox_path, '/') . "/\n";
        }

        $this->writeRootFile("{$this->postfixDir}/virtual_domains", $domainContent);
        $this->writeRootFile("{$this->postfixDir}/virtual_mailboxes", $mailboxContent);
        $this->writeRootFile("{$this->postfixDir}/virtual_aliases", '');

        Process::run("sudo postmap {$this->postfixDir}/virtual_domains {$this->postfixDir}/virtual_mailboxes {$this->postfixDir}/virtual_aliases 2>&1");
        $this->configurePostfix();
        $this->openFirewallPorts();

        $check = Process::run('sudo postfix check 2>&1');
        if ($check->failed()) {
            throw new RuntimeException('Postfix config validation failed: ' . $this->safeOutput($check->output() ?: $check->errorOutput()));
        }

        Process::run('sudo systemctl restart postfix 2>&1');

        return ['success' => true, 'domains' => count($domains), 'mailboxes' => count($mailboxes)];
    }

    public function regenerateDovecotAuth(): array
    {
        $this->ensureVmailUser();
        $vmail = $this->vmailIdentity();
        $lines = [];

        foreach ($this->activeMailboxes() as $mailbox) {
            $home = dirname($mailbox->mailbox_path);
            $lines[] = implode(':', [
                $mailbox->email,
                $mailbox->password_hash,
                $vmail['uid'],
                $vmail['gid'],
                '',
                $home,
                '',
                'userdb_mail=maildir:' . $mailbox->mailbox_path,
            ]);
        }

        $this->writeRootFile($this->dovecotPasswd, implode("\n", $lines) . (empty($lines) ? '' : "\n"));
        Process::run('sudo chown root:dovecot ' . escapeshellarg($this->dovecotPasswd) . ' 2>/dev/null || sudo chown root:root ' . escapeshellarg($this->dovecotPasswd));
        Process::run('sudo chmod 640 ' . escapeshellarg($this->dovecotPasswd));

        $this->writeRootFile($this->dovecotConf, $this->dovecotConfig());

        $check = Process::run('sudo doveconf -n >/dev/null 2>&1');
        if ($check->failed()) {
            throw new RuntimeException('Dovecot config validation failed.');
        }

        Process::run('sudo systemctl restart dovecot 2>&1');
        $this->openFirewallPorts();

        return ['success' => true, 'mailboxes' => count($lines)];
    }

    public function testMailboxAuth(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $script = "import imaplib,sys\nhost='127.0.0.1'\nuser=sys.argv[1]\npw=sys.stdin.read().rstrip('\\n')\nM=imaplib.IMAP4(host,143)\ntry:\n    M.login(user,pw)\n    M.logout()\n    print('OK')\nexcept Exception as e:\n    print('FAIL')\n    sys.exit(1)\n";
        $tmp = tempnam(sys_get_temp_dir(), 'imap-auth-');
        file_put_contents($tmp, $script);
        $cmd = 'printf %s ' . escapeshellarg($password) . ' | python3 ' . escapeshellarg($tmp) . ' ' . escapeshellarg($email) . ' 2>&1';
        $result = Process::timeout(20)->run($cmd);
        @unlink($tmp);

        return [
            'success' => $result->successful() && str_contains($result->output(), 'OK'),
            'email' => $email,
        ];
    }

    public function testLocalDelivery(string $fromEmail, string $toEmail): array
    {
        $to = $this->mailbox($toEmail);
        $before = $this->maildirCount($to->mailbox_path);
        $subject = 'OpenPanel local mail validation ' . date('YmdHis');
        $message = "From: {$fromEmail}\nTo: {$toEmail}\nSubject: {$subject}\n\nOpenPanel validation message.\n";
        $tmp = tempnam(sys_get_temp_dir(), 'mail-msg-');
        file_put_contents($tmp, $message);

        $send = Process::timeout(30)->run('sudo sendmail -f ' . escapeshellarg($fromEmail) . ' ' . escapeshellarg($toEmail) . ' < ' . escapeshellarg($tmp) . ' 2>&1');
        @unlink($tmp);
        sleep(2);

        $after = $this->maildirCount($to->mailbox_path);

        return [
            'success' => $send->successful() && $after > $before,
            'from' => $fromEmail,
            'to' => $toEmail,
            'delivered' => $after > $before,
        ];
    }

    public function dnsHelperRecords(string $domain, string $hostname): array
    {
        $domain = $this->normalizeDomain($domain);
        $hostname = trim($hostname) ?: "mail.{$domain}";

        return [
            'mx' => "{$domain}. 3600 IN MX 10 {$hostname}.",
            'spf' => "{$domain}. 3600 IN TXT \"v=spf1 mx a -all\"",
            'dmarc' => "_dmarc.{$domain}. 3600 IN TXT \"v=DMARC1; p=quarantine; rua=mailto:admin@{$domain}\"",
            'dkim' => 'not implemented in 0.1.0-beta',
        ];
    }

    public function accountForDomain(string $domain): object
    {
        $domain = $this->normalizeDomain($domain);
        $account = DB::table('accounts')->where('domain', $domain)->first();
        if (!$account) {
            throw new RuntimeException("Hosting account not found for {$domain}.");
        }
        return $account;
    }

    protected function activeMailboxes()
    {
        return DB::table('email_accounts')
            ->join('accounts', 'email_accounts.account_id', '=', 'accounts.id')
            ->whereNull('email_accounts.deleted_at')
            ->where('email_accounts.status', 'active')
            ->where('accounts.status', 'active')
            ->orderBy('email_accounts.email')
            ->select('email_accounts.*')
            ->get();
    }

    protected function mailbox(string $email): EmailAccount
    {
        $email = strtolower(trim($email));
        $mailbox = EmailAccount::where('email', $email)->first();
        if (!$mailbox) {
            throw new RuntimeException('Mailbox not found.');
        }
        return $mailbox;
    }

    protected function mailboxPath(string $username, string $domain, string $localPart): string
    {
        return "/home/{$username}/mail/{$domain}/{$localPart}/Maildir";
    }

    protected function createMaildir(string $mailboxPath): void
    {
        $this->ensureVmailUser();
        foreach (['', '/cur', '/new', '/tmp'] as $suffix) {
            Process::run('sudo mkdir -p ' . escapeshellarg($mailboxPath . $suffix));
        }

        $mailboxBase = dirname($mailboxPath);
        $domainBase = dirname($mailboxBase);
        $mailBase = dirname($domainBase);

        Process::run('sudo chmod 711 ' . escapeshellarg($mailBase) . ' ' . escapeshellarg($domainBase));
        Process::run('sudo chown vmail:mail ' . escapeshellarg($mailboxBase));
        Process::run('sudo chown -R vmail:mail ' . escapeshellarg($mailboxBase));
        Process::run('sudo chmod -R 700 ' . escapeshellarg($mailboxBase));
    }

    protected function ensureVmailUser(): void
    {
        if (Process::run('id vmail >/dev/null 2>&1')->successful()) {
            return;
        }

        $created = Process::run('sudo useradd -r -u 5000 -g mail -d /var/empty -s /sbin/nologin vmail 2>&1');
        if ($created->failed() && !Process::run('id vmail >/dev/null 2>&1')->successful()) {
            throw new RuntimeException('Unable to create vmail user.');
        }
    }

    protected function vmailIdentity(): array
    {
        $uid = trim(Process::run('id -u vmail')->output());
        $gid = trim(Process::run('id -g vmail')->output());

        return ['uid' => $uid ?: '5000', 'gid' => $gid ?: '12'];
    }

    protected function configurePostfix(): void
    {
        $vmail = $this->vmailIdentity();
        $settings = [
            'inet_interfaces' => 'all',
            'home_mailbox' => 'Maildir/',
            'virtual_mailbox_domains' => "hash:{$this->postfixDir}/virtual_domains",
            'virtual_mailbox_maps' => "hash:{$this->postfixDir}/virtual_mailboxes",
            'virtual_alias_maps' => "hash:{$this->postfixDir}/virtual_aliases",
            'virtual_transport' => 'lmtp:unix:private/dovecot-lmtp',
            'virtual_uid_maps' => "static:{$vmail['uid']}",
            'virtual_gid_maps' => "static:{$vmail['gid']}",
            'smtpd_sasl_type' => 'dovecot',
            'smtpd_sasl_path' => 'private/auth',
            'smtpd_sasl_auth_enable' => 'yes',
            'smtpd_sasl_security_options' => 'noanonymous',
            'smtpd_relay_restrictions' => 'permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination',
            'smtpd_recipient_restrictions' => 'permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination',
            'smtpd_reject_unlisted_recipient' => 'yes',
            'smtpd_tls_security_level' => 'may',
            'smtpd_tls_auth_only' => 'no',
            'smtpd_tls_cert_file' => '/etc/pki/tls/certs/openpanel.crt',
            'smtpd_tls_key_file' => '/etc/pki/tls/private/openpanel.key',
        ];

        foreach ($settings as $key => $value) {
            Process::run('sudo postconf -e ' . escapeshellarg("{$key} = {$value}") . ' 2>&1');
        }

        $master = $this->readRootFile('/etc/postfix/master.cf');
        if (!preg_match('/^submission\s+inet\s+/m', $master)) {
            $master = rtrim($master) . "\n" . $this->submissionMasterBlock();
            $this->writeRootFile('/etc/postfix/master.cf', $master);
        }
    }

    protected function openFirewallPorts(): void
    {
        if (!Process::run('firewall-cmd --state >/dev/null 2>&1')->successful()) {
            return;
        }

        foreach (['25/tcp', '143/tcp', '587/tcp', '993/tcp'] as $port) {
            Process::run('sudo firewall-cmd --permanent --add-port=' . escapeshellarg($port) . ' >/dev/null 2>&1');
        }
        Process::run('sudo firewall-cmd --reload >/dev/null 2>&1');
    }

    protected function submissionMasterBlock(): string
    {
        return <<<'CONF'
submission inet n       -       n       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=may
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_sasl_security_options=noanonymous
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject
  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject

CONF;
    }

    protected function dovecotConfig(): string
    {
        return <<<'CONF'
auth_mechanisms = plain login
disable_plaintext_auth = no
mail_location = maildir:%h/Maildir
first_valid_uid = 500
first_valid_gid = 1

passdb {
  driver = passwd-file
  args = username_format=%u /etc/dovecot/openpanel-passwd
}

userdb {
  driver = passwd-file
  args = username_format=%u /etc/dovecot/openpanel-passwd
}

service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
}

service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}

protocol lmtp {
  postmaster_address = postmaster@localhost
}
CONF;
    }

    protected function hashPassword(string $password): string
    {
        $salt = substr(strtr(base64_encode(random_bytes(12)), '+', '.'), 0, 16);
        return '{SHA512-CRYPT}' . crypt($password, '$6$rounds=5000$' . $salt . '$');
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        if (!preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new RuntimeException('Invalid email domain.');
        }
        return $domain;
    }

    protected function normalizeLocalPart(string $localPart): string
    {
        $localPart = strtolower(trim($localPart));
        if (!preg_match('/^[a-z0-9._%+-]{1,64}$/', $localPart)) {
            throw new RuntimeException('Invalid mailbox local part.');
        }
        return $localPart;
    }

    protected function writeRootFile(string $path, string $content): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'opmail-');
        file_put_contents($tmp, $content);
        Process::run('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path));
        @unlink($tmp);
    }

    protected function readRootFile(string $path): string
    {
        if (is_readable($path)) {
            return (string) file_get_contents($path);
        }
        $result = Process::run('sudo cat ' . escapeshellarg($path) . ' 2>/dev/null');
        return $result->successful() ? $result->output() : '';
    }

    protected function maildirCount(string $mailboxPath): int
    {
        $result = Process::run('sudo find ' . escapeshellarg($mailboxPath . '/new') . ' ' . escapeshellarg($mailboxPath . '/cur') . ' -type f 2>/dev/null | wc -l');
        return (int) trim($result->output());
    }

    protected function safeOutput(string $output): string
    {
        $output = preg_replace('/\b(pass(word)?|secret|token)=\S+/i', '$1=[redacted]', $output);
        return mb_substr(trim(preg_replace('/\s+/', ' ', $output)) ?: 'unknown error', 0, 500);
    }
}
