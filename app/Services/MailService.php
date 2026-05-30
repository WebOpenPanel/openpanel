<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MailService
{
    const POSTFIX_MAIN = '/etc/postfix/main.cf';
    const POSTFIX_MASTER = '/etc/postfix/master.cf';
    const POSTFIX_TRANSPORT = '/etc/postfix/transport';
    const VIRTUAL_FILE = '/etc/postfix/virtual';
    const ALIASES_FILE = '/etc/aliases';
    const DOVECOT_CONF = '/etc/dovecot/dovecot.conf';
    const MAIL_LOG = '/var/log/maillog';
    const MAIL_QUEUE_DIR = '/var/spool/postfix/maildrop/';

    public static function getMailQueue(): array
    {
        $output = ShellService::exec('postqueue -p 2>/dev/null');
        return ['raw' => $output];
    }

    public static function flushMailQueue(): string
    {
        return ShellService::exec('postfix flush 2>&1');
    }

    public static function deleteMailQueue(): string
    {
        return ShellService::exec('postsuper -d ALL 2>&1');
    }

    public static function deleteDeferredQueue(): string
    {
        return ShellService::exec('postsuper -d ALL deferred 2>&1');
    }

    public static function holdMailQueue(): string
    {
        return ShellService::exec('postsuper -h ALL 2>&1');
    }

    public static function releaseMailQueue(): string
    {
        return ShellService::exec('postsuper -H ALL 2>&1');
    }

    public static function getMailQueueCount(): int
    {
        $output = ShellService::exec('find /var/spool/postfix/deferred /var/spool/postfix/active /var/spool/postfix/maildrop -type f 2>/dev/null | wc -l');
        return (int) $output;
    }

    public static function getPostfixMainConf(): string
    {
        return ShellService::readFile(self::POSTFIX_MAIN);
    }

    public static function savePostfixMainConf(string $content): bool
    {
        return ShellService::writeFile(self::POSTFIX_MAIN, $content);
    }

    public static function getPostfixMasterConf(): string
    {
        return ShellService::readFile(self::POSTFIX_MASTER);
    }

    public static function savePostfixMasterConf(string $content): bool
    {
        return ShellService::writeFile(self::POSTFIX_MASTER, $content);
    }

    public static function getVirtualUsers(): array
    {
        $content = ShellService::readFile(self::VIRTUAL_FILE);
        $users = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (!empty($line) && $line[0] !== '#') {
                $parts = preg_split('/\s+/', $line, 2);
                if (count($parts) === 2) {
                    $users[] = ['address' => $parts[0], 'destination' => $parts[1]];
                }
            }
        }
        return $users;
    }

    public static function getDovecotConf(): string
    {
        return ShellService::readFile(self::DOVECOT_CONF);
    }

    public static function saveDovecotConf(string $content): bool
    {
        return ShellService::writeFile(self::DOVECOT_CONF, $content);
    }

    public static function getMailLog(int $lines = 100): string
    {
        return ShellService::exec("tail -n {$lines} " . self::MAIL_LOG . " 2>/dev/null");
    }

    public static function searchMailLog(string $search, int $lines = 50): string
    {
        return ShellService::exec("grep -i " . escapeshellarg($search) . " " . self::MAIL_LOG . " 2>/dev/null | tail -n {$lines}");
    }

    public static function pipeToScriptAdd(string $email, string $domain, string $username, string $phpPath, string $scriptPath): bool
    {
        $now = date('Y-m-d H:i:s');

        DB::connection('postfix')->statement("CREATE TABLE IF NOT EXISTS alias_pipe (
            address VARCHAR(255) NOT NULL,
            transport VARCHAR(255) NOT NULL,
            domain VARCHAR(255) NOT NULL,
            php_path VARCHAR(255) NOT NULL,
            script_path VARCHAR(255) NOT NULL,
            username VARCHAR(40) NOT NULL,
            created datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
            modified datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
            active tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (address)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1");

        DB::connection('postfix')->table('alias_pipe')->updateOrInsert(
            ['address' => $email],
            [
                'transport' => $email,
                'domain' => $domain,
                'php_path' => $phpPath,
                'script_path' => $scriptPath,
                'username' => $username,
                'created' => $now,
                'modified' => $now,
                'active' => 1,
            ]
        );

        if (!file_exists($phpPath)) {
            $phpPath = '/usr/local/bin/php';
        }

        $pipeEntry = "{$email} unix - n n - - pipe  flags=Rq user={$username} argv={$phpPath} -q {$scriptPath} -f \$\\{sender\\} -- \$\\{recipient\\}";

        ShellService::exec("grep '^{$email} ' " . self::POSTFIX_MASTER . " || echo " . escapeshellarg($pipeEntry) . " >> " . self::POSTFIX_MASTER);
        ShellService::exec("grep '^{$email} ' " . self::POSTFIX_TRANSPORT . " || echo '{$email} {$email}:' >> " . self::POSTFIX_TRANSPORT);
        ShellService::exec("postmap " . self::POSTFIX_TRANSPORT);

        ServerService::serviceAction('reload', 'postfix');
        return true;
    }

    public static function pipeToScriptRemove(string $email): bool
    {
        ShellService::exec("sed -i '/^{$email} /d' " . self::POSTFIX_MASTER);
        ShellService::exec("sed -i '/^{$email} /d' " . self::POSTFIX_TRANSPORT);
        try {
            DB::connection('postfix')->table('alias_pipe')->where('address', $email)->delete();
        } catch (\Exception $e) {}
        ShellService::exec("postmap " . self::POSTFIX_TRANSPORT);
        ServerService::serviceAction('reload', 'postfix');
        return true;
    }

    public static function pipeToScriptSuspend(string $email): bool
    {
        ShellService::exec("sed -i '/^{$email} /d' " . self::POSTFIX_MASTER);
        ShellService::exec("sed -i '/^{$email} /d' " . self::POSTFIX_TRANSPORT);
        try {
            DB::connection('postfix')->table('alias_pipe')->where('address', $email)->update(['active' => 0]);
        } catch (\Exception $e) {}
        ShellService::exec("postmap " . self::POSTFIX_TRANSPORT);
        ServerService::serviceAction('reload', 'postfix');
        return true;
    }

    public static function pipeToScriptUnsuspend(string $email): bool
    {
        try {
            $pipe = DB::connection('postfix')->table('alias_pipe')->where('address', $email)->first();
        } catch (\Exception $e) {
            return false;
        }
        if (!$pipe) return false;

        $phpPath = $pipe->php_path ?: '/usr/local/bin/php';
        $pipeEntry = "{$email} unix - n n - - pipe  flags=Rq user={$pipe->username} argv={$phpPath} -q {$pipe->script_path} -f \$\\{sender\\} -- \$\\{recipient\\}";

        ShellService::exec("grep '^{$email} ' " . self::POSTFIX_MASTER . " || echo " . escapeshellarg($pipeEntry) . " >> " . self::POSTFIX_MASTER);
        ShellService::exec("grep '^{$email} ' " . self::POSTFIX_TRANSPORT . " || echo '{$email} {$email}:' >> " . self::POSTFIX_TRANSPORT);
        try {
            DB::connection('postfix')->table('alias_pipe')->where('address', $email)->update(['active' => 1]);
        } catch (\Exception $e) {}
        ShellService::exec("postmap " . self::POSTFIX_TRANSPORT);
        ServerService::serviceAction('reload', 'postfix');
        return true;
    }

    public static function pipeToScriptRemoveDomain(string $domain): bool
    {
        try {
            $pipes = DB::connection('postfix')->table('alias_pipe')->where('domain', $domain)->get();
            foreach ($pipes as $pipe) {
                self::pipeToScriptRemove($pipe->address);
            }
        } catch (\Exception $e) {}
        return true;
    }

    public static function pipeToScriptRemoveAccount(string $username): bool
    {
        try {
            $pipes = DB::connection('postfix')->table('alias_pipe')->where('username', $username)->get();
            foreach ($pipes as $pipe) {
                self::pipeToScriptRemove($pipe->address);
            }
        } catch (\Exception $e) {}
        return true;
    }

    public static function pipeToScriptSuspendAccount(string $username): bool
    {
        try {
            $pipes = DB::connection('postfix')->table('alias_pipe')->where('username', $username)->get();
            foreach ($pipes as $pipe) {
                self::pipeToScriptSuspend($pipe->address);
            }
        } catch (\Exception $e) {}
        return true;
    }

    public static function pipeToScriptUnsuspendAccount(string $username): bool
    {
        try {
            $pipes = DB::connection('postfix')->table('alias_pipe')->where('username', $username)->get();
            foreach ($pipes as $pipe) {
                self::pipeToScriptUnsuspend($pipe->address);
            }
        } catch (\Exception $e) {}
        return true;
    }

    public static function pipeToScriptRebuild(): void
    {
        try {
            $pipes = DB::connection('postfix')->table('alias_pipe')->where('active', 1)->get();
            foreach ($pipes as $pipe) {
                $phpPath = $pipe->php_path ?: '/usr/local/bin/php';
                $pipeEntry = "{$pipe->address} unix - n n - - pipe  flags=Rq user={$pipe->username} argv={$phpPath} -q {$pipe->script_path} -f \$\\{sender\\} -- \$\\{recipient\\}";
                ShellService::exec("grep '^{$pipe->address} ' " . self::POSTFIX_MASTER . " || echo " . escapeshellarg($pipeEntry) . " >> " . self::POSTFIX_MASTER);
                ShellService::exec("grep '^{$pipe->address} ' " . self::POSTFIX_TRANSPORT . " || echo '{$pipe->address} {$pipe->address}:' >> " . self::POSTFIX_TRANSPORT);
            }
        } catch (\Exception $e) {}
        ShellService::exec("postmap " . self::POSTFIX_TRANSPORT);
    }

    public static function getMailExplorer(string $directory = '/var/vmail'): array
    {
        $items = [];
        if (!is_dir($directory)) return $items;
        foreach (ShellService::dirList($directory) as $item) {
            $fullPath = $directory . '/' . $item;
            $items[] = [
                'name' => $item,
                'is_dir' => is_dir($fullPath),
                'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                'modified' => filemtime($fullPath),
            ];
        }
        return $items;
    }

    public static function getMxRecords(string $domain): array
    {
        $output = ShellService::exec("dig MX " . escapeshellarg($domain) . " +short 2>/dev/null");
        $records = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $parts = preg_split('/\s+/', $line);
                $records[] = ['priority' => (int)($parts[0] ?? 0), 'exchange' => $parts[1] ?? ''];
            }
        }
        return $records;
    }

    public static function setMxEntry(string $domain, int $priority, string $exchange): bool
    {
        $zoneFile = '/var/named/' . $domain . '.db';
        if (!file_exists($zoneFile)) return false;

        ShellService::exec("sed -i '/MX/d' " . escapeshellarg($zoneFile));
        $mxEntry = "@       14400   IN      MX      {$priority}       {$exchange}.";
        file_put_contents($zoneFile, "\n" . $mxEntry . "\n", FILE_APPEND | LOCK_EX);

        DnsService::updateSerial($domain);
        ServerService::serviceAction('reload', 'named');
        return true;
    }

    public static function getPolicydStatus(): array
    {
        $output = ShellService::exec("systemctl is-active policyd 2>/dev/null");
        return ['active' => trim($output) === 'active'];
    }

    public static function policydAction(string $action): string
    {
        return ServerService::serviceAction($action, 'policyd');
    }

    public static function getAntispamConfig(): string
    {
        return ShellService::readFile('/etc/postfix/main.cf');
    }

    public static function getRblCheck(): string
    {
        return ShellService::exec("rblcheck 2>/dev/null || echo 'rblcheck not installed'");
    }

    public static function getPostfixListManager(): array
    {
        $output = ShellService::exec("postconf -n 2>/dev/null");
        return ['raw' => $output];
    }

    public static function restartPostfix(): string
    {
        return ServerService::serviceAction('restart', 'postfix');
    }

    public static function restartDovecot(): string
    {
        return ServerService::serviceAction('restart', 'dovecot');
    }

    public static function getMailServerStatus(): array
    {
        $postfix = ShellService::exec("systemctl is-active postfix 2>/dev/null");
        $dovecot = ShellService::exec("systemctl is-active dovecot 2>/dev/null");
        $spamassassin = ShellService::exec("systemctl is-active spamassassin 2>/dev/null");
        $opendkim = ShellService::exec("systemctl is-active opendkim 2>/dev/null");

        return [
            'postfix' => trim($postfix) === 'active',
            'dovecot' => trim($dovecot) === 'active',
            'spamassassin' => trim($spamassassin) === 'active',
            'opendkim' => trim($opendkim) === 'active',
        ];
    }

    public static function getCustomSender(): array
    {
        $conf = ShellService::readFile('/usr/local/openpanel/.conf/custom_sender.conf');
        if (empty($conf)) return [];
        return json_decode($conf, true) ?? [];
    }

    public static function setCustomSender(string $domain, string $email, string $name): bool
    {
        $senders = self::getCustomSender();
        $senders[$domain] = ['email' => $email, 'name' => $name];
        return ShellService::writeFile('/usr/local/openpanel/.conf/custom_sender.conf', json_encode($senders, JSON_PRETTY_PRINT));
    }

    public static function removeCustomSender(string $domain): bool
    {
        $senders = self::getCustomSender();
        unset($senders[$domain]);
        return ShellService::writeFile('/usr/local/openpanel/.conf/custom_sender.conf', json_encode($senders, JSON_PRETTY_PRINT));
    }
}
