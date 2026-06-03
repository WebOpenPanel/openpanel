<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DnsService
{
    const NAMED_CONF = '/etc/named.conf';
    const ZONE_BASE = '/var/named/';
    const ZONE_TEMPLATE_DIR = '/usr/local/openpanel/htdocs/resources/conf/dns/bind/zones/';
    const MAIN_TEMPLATE_DIR = '/usr/local/openpanel/htdocs/resources/conf/dns/bind/main/';
    const SLAVE_DNS_CONF = '/usr/local/openpanel/htdocs/resources/conf/slave_dns/slave_dns_active_conf.json';
    const OPENDKIM_DIR = '/etc/opendkim/';
    const DKIM_TRUSTED_HOSTS = '/etc/opendkim/TrustedHosts';
    const DKIM_KEY_TABLE = '/etc/opendkim/KeyTable';
    const DKIM_SIGNING_TABLE = '/etc/opendkim/SigningTable';
    const DKIM_USERKEYS_DIR = '/etc/opendkim/userkeys/';
    const DKIM_KEYS_DIR = '/etc/opendkim/keys/';
    const DNS_SLAVE_LOG = '/var/log/openpanel/dns_slave.log';

    public static function getZoneFile(string $domain): string
    {
        return ShellService::readFile(self::ZONE_BASE . $domain . '.db');
    }

    public static function saveZoneFile(string $domain, string $content): bool
    {
        return ShellService::writeFile(self::ZONE_BASE . $domain . '.db', $content);
    }

    public static function zoneExists(string $domain): bool
    {
        return file_exists(self::ZONE_BASE . $domain . '.db');
    }

    public static function listZones(): array
    {
        $zones = [];
        if (!is_dir(self::ZONE_BASE)) return $zones;
        foreach (ShellService::dirList(self::ZONE_BASE) as $file) {
            if (preg_match('/^(.+)\.db$/', $file, $m) && !preg_match('/\.restore$/', $file)) {
                $zones[] = $m[1];
            }
        }
        sort($zones);
        return $zones;
    }

    public static function getZoneRecords(string $domain): array
    {
        $content = self::getZoneFile($domain);
        if (empty($content)) return [];
        $records = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === ';' || preg_match('/^\$/', $line) || preg_match('/^\(/', $line)) continue;
            if (preg_match('/^(\S+)\s+(\d+)\s+(IN)\s+(A|AAAA|CNAME|MX|TXT|NS|SRV|CAA|PTR|SOA)\s+(.+)$/', $line, $m)) {
                $records[] = [
                    'name' => $m[1],
                    'ttl' => (int) $m[2],
                    'class' => $m[3],
                    'type' => $m[4],
                    'value' => trim($m[5]),
                ];
            }
        }
        return $records;
    }

    public static function getZone(string $domain): ?string
    {
        $cpanelConf = '/usr/local/openpanel/htdocs/resources/conf/cpanel_dns/cpanel_dns_active_conf.json';
        if (file_exists($cpanelConf)) {
            self::cpanelGetZoneSave($domain);
            if (file_exists(self::ZONE_BASE . $domain . '.db')) {
                return file_get_contents(self::ZONE_BASE . $domain . '.db');
            }
            return null;
        }
        if (file_exists(self::ZONE_BASE . $domain . '.db')) {
            return file_get_contents(self::ZONE_BASE . $domain . '.db');
        }
        return null;
    }

    protected static function cpanelGetZoneSave(string $domain): void
    {
        $conf = self::cpanelDnsConfig();
        if (!$conf) return;
        $data = array_merge($conf, [
            'zone' => $domain,
            'hosts_seen' => trim(ShellService::exec('hostname -f') ?: 'localhost'),
            'dnsuniqid' => 'OP_' . self::generateRandomString(),
        ]);
        $url = 'https://' . ($conf['CLUSTER_HOSTNAME'] ?? '') . '/scripts2/getzone_local';
        $result = self::cpanelCurl($url, $data);
        if (!empty($result)) {
            file_put_contents(self::ZONE_BASE . $domain . '.db', $result, LOCK_EX);
        }
    }

    protected static function cpanelDnsConfig(): ?array
    {
        $confPath = '/usr/local/openpanel/htdocs/resources/conf/cpanel_dns/cpanel_dns_active_conf.json';
        if (file_exists($confPath)) {
            return json_decode(file_get_contents($confPath), true);
        }
        return null;
    }

    protected static function cpanelCurl(string $url, array $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Host: ' . ($data['CLUSTER_HOSTNAME'] ?? ''),
            'User-Agent: OpenPanel DNS-SYNC',
            'Authorization: whm ' . ($data['USERNAME'] ?? '') . ':' . ($data['KEY'] ?? ''),
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public static function generateRandomString(int $length = 28): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $result;
    }

    public static function dnsEmail(string $email): string
    {
        return preg_replace('/\@/', '.', $email);
    }

    public static function addZone(string $domain, string $ip, string $email): bool
    {
        if (self::zoneExists($domain)) return false;

        $tplFile = self::resolveZoneTemplatePath();
        $mainTpl = file_get_contents($tplFile);
        if (!$mainTpl) return false;

        $domainEmail = self::dnsEmail($domain);
        $nameservers = self::getNameservers();

        $zoneContent = "\n" . $mainTpl;
        $zoneContent = str_replace('%domain%', $domain, $zoneContent);
        $zoneContent = str_replace('%dns-email%', 'postmaster.' . $domainEmail, $zoneContent);
        $zoneContent = str_replace('%ns1%', $nameservers['ns1'], $zoneContent);
        $zoneContent = str_replace('%ns2%', $nameservers['ns2'], $zoneContent);
        $zoneContent = str_replace('%ip%', $ip, $zoneContent);

        file_put_contents(self::ZONE_BASE . $domain . '.db', $zoneContent);

        self::appendNsRecordsIfNeeded($domain, $nameservers);
        self::addDomainToNamedConf($domain);
        self::hookAddDkimNamed($domain);
        self::externalHooks('dns', 'dns_new_zone_add', $domain);
        self::updateSerial($domain);
        self::dnsManagerAddNewDomain($domain);
        self::manageServices('reload', ['named']);

        return true;
    }

    public static function addZoneForce(string $domain, string $ip, string $email, string $action = 'reload'): bool
    {
        $tplFile = self::resolveZoneTemplatePath();
        $mainTpl = file_get_contents($tplFile);
        if (!$mainTpl) return false;

        $domainEmail = self::dnsEmail($domain);
        $nameservers = self::getNameservers();

        $zoneContent = "\n" . $mainTpl;
        $zoneContent = str_replace('%domain%', $domain, $zoneContent);
        $zoneContent = str_replace('%dns-email%', 'postmaster.' . $domainEmail, $zoneContent);
        $zoneContent = str_replace('%ns1%', $nameservers['ns1'], $zoneContent);
        $zoneContent = str_replace('%ns2%', $nameservers['ns2'], $zoneContent);
        $zoneContent = str_replace('%ip%', $ip, $zoneContent);

        file_put_contents(self::ZONE_BASE . $domain . '.db', $zoneContent);

        self::appendNsRecordsIfNeeded($domain, $nameservers);
        self::addDomainToNamedConf($domain);
        self::hookAddDkimNamed($domain);
        self::externalHooks('dns', 'dns_new_zone_add', $domain);
        self::updateSerial($domain);
        self::dnsManagerAddNewDomain($domain);

        if ($action === 'reload') {
            self::manageServices('reload', ['named']);
        } elseif ($action === 'restart') {
            self::manageServices('restart', ['named']);
        }

        return true;
    }

    public static function addSubdomainZone(string $domain, string $subdomain, string $ip, string $action = 'reload'): bool
    {
        if ($subdomain === '*') {
            $entry = "\n{$subdomain}     14400   IN      A       {$ip}  ; #subdomain {$subdomain}\n";
        } else {
            $entry = "\n{$subdomain}     14400   IN      A       {$ip}  ; #subdomain {$subdomain}\n";
            $entry .= "www.{$subdomain}     14400   IN      A       {$ip}  ; #subdomain {$subdomain}\n";
        }

        if (file_exists(self::ZONE_BASE . $domain . '.db')) {
            $existing = ShellService::exec(
                "grep \"^{$subdomain}\\s\\+\\|^{$subdomain}.{$domain}.\\s\\+\\|^www.{$subdomain}\\s\\+\\|^{$subdomain}.{$domain}.\\s\\+\" " . self::ZONE_BASE . "{$domain}.db"
            );
            $existing = preg_replace("/^\s+|\s+$/", '', $existing);
            if (empty($existing)) {
                file_put_contents(self::ZONE_BASE . $domain . '.db', $entry, FILE_APPEND | LOCK_EX);
                self::externalHooks('dns', 'dns_new_subdomain_add', $domain);
                self::updateSerial($domain);
            }
        }

        if ($action === 'reload') {
            self::manageServices('reload', ['named']);
        }

        return true;
    }

    public static function removeSubdomain(string $domain, string $subdomain): bool
    {
        if (empty($domain) || !file_exists(self::ZONE_BASE . $domain . '.db')) return false;

        ShellService::exec("sed -i '/^{$subdomain} /d' " . self::ZONE_BASE . "{$domain}.db");
        ShellService::exec("sed -i '/^{$subdomain}.{$domain}. /d' " . self::ZONE_BASE . "{$domain}.db");
        ShellService::exec("sed -i '/^www.{$subdomain} /d' " . self::ZONE_BASE . "{$domain}.db");
        ShellService::exec("sed -i '/^www.{$subdomain}.{$domain}. /d' " . self::ZONE_BASE . "{$domain}.db");

        $fullDomain = $subdomain . '.' . $domain;
        if (file_exists(self::DKIM_USERKEYS_DIR . $fullDomain . '/') && !empty($subdomain)) {
            ShellService::exec("test -h " . self::DKIM_USERKEYS_DIR . "{$fullDomain} || rm -Rf " . self::DKIM_USERKEYS_DIR . "{$fullDomain}");
        }
        if (file_exists(self::DKIM_SIGNING_TABLE)) {
            ShellService::exec("sed -i \"/\\^*\\@{$fullDomain} /d\" " . self::DKIM_SIGNING_TABLE);
        }
        if (file_exists(self::DKIM_KEY_TABLE) && !empty($subdomain)) {
            ShellService::exec("sed -i '/^default._domainkey.{$fullDomain} /d' " . self::DKIM_KEY_TABLE);
        }
        if (file_exists(self::DKIM_TRUSTED_HOSTS) && !empty($subdomain)) {
            ShellService::exec("sed -i '/^{$fullDomain}[[:blank:]]*\$/d' " . self::DKIM_TRUSTED_HOSTS);
        }

        self::externalHooks('dns', 'dns_subdomain_remove', $domain);
        self::updateSerial($domain);
        self::manageServices('reload', ['named']);

        return true;
    }

    public static function deleteZone(string $domain): bool
    {
        $namedConf = self::NAMED_CONF;
        $timestamp = date('Y-m-d_h:m:s');
        ShellService::exec("cp {$namedConf} {$namedConf}_bkp_{$timestamp}");

        $content = file_get_contents($namedConf);
        $grepResult = ShellService::exec("grep -n 'zone \"{$domain}\"' {$namedConf}");

        if (empty(trim($grepResult))) {
            return false;
        }

        $matchedLines = explode("\n", $grepResult);
        $lines = explode("\n", $content);
        $totalRemoved = 0;

        foreach ($matchedLines as $match) {
            if (empty(trim($match))) continue;
            $parts = explode(':', $match);
            $startLine = intval($parts[0]) - 1 - $totalRemoved;
            $removeCount = 0;
            $lineCount = count($lines);
            $prevRemoved = 0;

            for ($i = $startLine; $i < $lineCount; $i++) {
                $currentLine = trim($lines[$i]);
                preg_match("/zone {$domain}/", $currentLine);

                if ($i === $startLine) {
                    $prevLine = $lines[$i - 1];
                    if (preg_match("/zone {$domain}/", $prevLine)) {
                        $removeCount++;
                        $prevRemoved = 1;
                    }
                }
                $removeCount++;

                if (preg_match("/};$/", $currentLine)) {
                    $nextLine = $lines[$i + 1];
                    if (preg_match("/zone_end {$domain}/", $nextLine)) {
                        $removeCount++;
                    }
                    break;
                }
            }

            $totalRemoved += $removeCount;
            array_splice($lines, $startLine - $prevRemoved, $removeCount);
        }

        $newContent = implode("\n", $lines);
        file_put_contents($namedConf, $newContent);

        self::removeDkimForDomain($domain);

        ShellService::exec("rm -f " . self::ZONE_BASE . "{$domain}.db");

        self::externalHooks('dns', 'dns_zone_remove', $domain);
        self::dnsManagerRemoveDomain($domain);

        return true;
    }

    public static function rebuildAllZones(): string
    {
        $output = '';
        $users = DB::connection('mysql')->table('user')->where('id', '!=', '')->get();

        foreach ($users as $user) {
            $dnsEmail = self::dnsEmail($user->email ?? '');
            self::addZoneForce($user->domain, $user->ip_address, $dnsEmail, 'none');

            $domains = DB::connection('mysql')->table('domains')->where('user', $user->username)->get();
            foreach ($domains as $d) {
                self::addZoneForce($d->domain, $user->ip_address, $dnsEmail, 'none');

                $subdomains = DB::connection('mysql')->table('subdomains')
                    ->where('user', $user->username)
                    ->where('domain', $d->domain)
                    ->get();
                foreach ($subdomains as $sub) {
                    self::addSubdomainZone($sub->domain, $sub->subdomain, $user->ip_address, 'none');
                }
            }
        }

        self::manageServices('restart', ['named']);
        return $output;
    }

    public static function rebuildZone(string $username, string $domain): bool
    {
        $users = DB::connection('mysql')->table('user')->where('username', $username)->get();
        if ($users->isEmpty()) return false;

        foreach ($users as $user) {
            $dnsEmail = self::dnsEmail($user->email ?? '');
            if ($domain === $user->domain) {
                self::addZoneForce($domain, $user->ip_address, $dnsEmail, 'restart');
            } else {
                $found = false;
                $domains = DB::connection('mysql')->table('domains')->where('user', $user->username)->get();
                foreach ($domains as $d) {
                    if ($domain === $d->domain) {
                        self::addZoneForce($d->domain, $user->ip_address, $dnsEmail, 'none');
                        $subdomains = DB::connection('mysql')->table('subdomains')
                            ->where('user', $user->username)
                            ->where('domain', $d->domain)
                            ->get();
                        foreach ($subdomains as $sub) {
                            self::addSubdomainZone($sub->domain, $sub->subdomain, $user->ip_address, 'none');
                        }
                        self::manageServices('restart', ['named']);
                        $found = true;
                        break;
                    }
                }
                if (!$found) return false;
            }
        }

        return true;
    }

    public static function addDomainToNamedConf(string $domain): void
    {
        $confTpl = self::MAIN_TEMPLATE_DIR . 'default.tpl';
        if (!file_exists($confTpl)) return;

        $entry = "\n" . file_get_contents($confTpl);
        $entry = str_replace('%domain%', $domain, $entry);

        $existing = ShellService::exec("grep /var/named/{$domain}.db " . self::NAMED_CONF);
        $existing = preg_replace("/^\s+|\s+$/", '', $existing);
        if (empty($existing)) {
            file_put_contents(self::NAMED_CONF, $entry, FILE_APPEND | LOCK_EX);
        }
    }

    public static function rebuildNamedConf(): string
    {
        $defaultOptions = "options {\r\n        listen-on port 53 { any; };\r\n        listen-on-v6 port 53 { ::1; };\r\n        directory       \"/var/named\";\r\n        dump-file       \"/var/named/data/cache_dump.db\";\r\n        statistics-file \"/var/named/data/named_stats.txt\";\r\n        memstatistics-file \"/var/named/data/named_mem_stats.txt\";\r\n        recursing-file  \"/var/named/data/named.recursing\";\r\n        secroots-file   \"/var/named/data/named.secroots\";\r\n        allow-query     { any; };\r\n\r\n        //Slave dns configuration, uncomment and set slave IP\r\n        //allow-transfer {111.112.113.114;};\r\n        //allow-recursion {111.112.113.114;};\r\n        //also-notify {111.112.113.114;};\r\n        //masterfile-format text;\r\n        \r\n        //Remove this line if using slave dns  configuration\r\n        allow-transfer { none; };\r\n\r\n        recursion no;\r\n\r\n        dnssec-enable yes;\r\n        dnssec-validation yes;\r\n\r\n        bindkeys-file \"/etc/named.iscdlv.key\";\r\n        managed-keys-directory \"/var/named/dynamic\";\r\n\r\n        pid-file \"/run/named/named.pid\";\r\n        session-keyfile \"/run/named/session.key\";\r\n};\r\n\r\nlogging {\r\n        channel default_debug {\r\n                file \"data/named.run\";\r\n                severity dynamic;\r\n        };\r\n};\r\n\r\nzone \".\" IN {\r\n        type hint;\r\n        file \"named.ca\";\r\n};\r\n\r\ninclude \"/etc/named.rfc1912.zones\";\r\ninclude \"/etc/named.root.key\";";

        file_put_contents(self::NAMED_CONF, $defaultOptions);

        $users = DB::connection('mysql')->table('user')->where('id', '!=', '')->get();
        foreach ($users as $user) {
            self::addDomainToNamedConf($user->domain);
            $domains = DB::connection('mysql')->table('domains')->where('user', $user->username)->get();
            foreach ($domains as $d) {
                self::addDomainToNamedConf($d->domain);
            }
        }

        self::manageServices('restart', ['named']);
        return 'Named.conf rebuilt successfully';
    }

    public static function updateSerial(string $domain): bool
    {
        $zoneFile = self::ZONE_BASE . $domain . '.db';
        if (!file_exists($zoneFile)) return false;

        $today = date('Ymd');
        $hour = substr(date('B'), 0, 2);
        $newSerial = $today . $hour;

        $output = ShellService::exec("egrep -ho '20[0-2][0-9][0-1][0-9][0-3][0-9][0-9]{2}\\s+' {$zoneFile}");
        $existingSerial = trim($output);

        if (!empty($existingSerial) && $newSerial <= $existingSerial) {
            $newSerial = $existingSerial + 1;
        }

        ShellService::exec("sed -i 's/20[0-2][0-9][0-1][0-9][0-3][0-9][0-9]\\{2\\}\\s\\+/{$newSerial} /' {$zoneFile}");
        ShellService::exec("sed -i '/^\$/d' {$zoneFile}");

        return true;
    }

    public static function getZoneTemplates(): array
    {
        $templates = [];
        if (!is_dir(self::ZONE_TEMPLATE_DIR)) return $templates;
        $result = ShellService::exec("cd " . self::ZONE_TEMPLATE_DIR . "; ls *.tpl");
        if (!empty(trim($result))) {
            $files = preg_split("/\n/", $result);
            $files = array_filter($files);
            foreach ($files as $file) {
                $templates[] = ['name' => $file, 'content' => ShellService::readFile(self::ZONE_TEMPLATE_DIR . $file)];
            }
        }
        return $templates;
    }

    public static function getZoneTemplate(string $name): string
    {
        return ShellService::readFile(self::ZONE_TEMPLATE_DIR . $name);
    }

    public static function saveZoneTemplate(string $name, string $content): bool
    {
        return ShellService::writeFile(self::ZONE_TEMPLATE_DIR . $name, $content);
    }

    public static function resolveZoneTemplatePath(): string
    {
        $defaultTpl = self::MAIN_TEMPLATE_DIR . 'default.tpl';
        $setting = self::getDefaultZoneTemplate();
        if ($setting) {
            $tplPath = self::ZONE_TEMPLATE_DIR . $setting;
            if (file_exists($tplPath)) {
                return $tplPath;
            }
        }
        return self::ZONE_TEMPLATE_DIR . 'default.tpl';
    }

    public static function getDefaultZoneTemplate(): ?string
    {
        $conf = ShellService::readFile('/usr/local/openpanel/.conf/settings.conf');
        if (preg_match('/default_dns_zone_template\s*=\s*(\S+)/', $conf, $m) && $m[1] !== '0') {
            return $m[1];
        }
        return null;
    }

    public static function getDefaultZoneTemplateContent(): string
    {
        $tpl = self::getDefaultZoneTemplate();
        if ($tpl) {
            return ShellService::readFile(self::ZONE_TEMPLATE_DIR . $tpl);
        }
        return '$TTL 14400
@       IN      SOA     %ns1%. %dns-email%. (
                        %serial%
                        3600
                        1800
                        1209600
                        86400 )

@       14400   IN      NS      %ns1%.
@       14400   IN      NS      %ns2%.
@       14400   IN      A       %ip%
localhost       14400   IN      A       127.0.0.1
@       14400   IN      MX      0       %domain%.
mail    14400   IN      A       %ip%
www     14400   IN      A       %ip%
ftp     14400   IN      A       %ip%
smtp    14400   IN      A       %ip%
pop     14400   IN      A       %ip%
imap    14400   IN      A       %ip%
';
    }

    public static function getNameservers(): array
    {
        try {
            $row = DB::connection('mysql')->table('nameserver')->first();
            if ($row) {
                return [
                    'ns1' => $row->ns1_name ?? 'ns1.example.com',
                    'ns2' => $row->ns2_name ?? 'ns2.example.com',
                    'ns1_ip' => $row->ns1_ip ?? '127.0.0.1',
                    'ns2_ip' => $row->ns2_ip ?? '127.0.0.1',
                ];
            }
        } catch (\Exception $e) {}
        return ['ns1' => 'ns1.example.com', 'ns2' => 'ns2.example.com', 'ns1_ip' => '127.0.0.1', 'ns2_ip' => '127.0.0.1'];
    }

    public static function setNameservers(string $ns1, string $ns2, string $ns1Ip, string $ns2Ip): bool
    {
        try {
            DB::connection('mysql')->table('nameserver')->update([
                'ns1_name' => $ns1,
                'ns2_name' => $ns2,
                'ns1_ip' => $ns1Ip,
                'ns2_ip' => $ns2Ip,
            ]);
        } catch (\Exception $e) {}
        return true;
    }

    protected static function appendNsRecordsIfNeeded(string $domain, array $nameservers): void
    {
        if (strpos($nameservers['ns1'], $domain) !== false || strpos($nameservers['ns2'], $domain) !== false) {
            $nsRecords = $nameservers['ns1'] . ".     14400   IN      A       " . $nameservers['ns1_ip'] . "  ; #ns1\n";
            $nsRecords .= $nameservers['ns2'] . ".     14400   IN      A       " . $nameservers['ns2_ip'] . "  ; #ns2\n";
            file_put_contents(self::ZONE_BASE . $domain . '.db', $nsRecords, FILE_APPEND | LOCK_EX);
        }
    }

    protected static function removeDkimForDomain(string $domain): void
    {
        if (file_exists(self::DKIM_USERKEYS_DIR . $domain . '/') && !empty($domain)) {
            ShellService::exec("test -h " . self::DKIM_USERKEYS_DIR . "{$domain} || rm -Rf " . self::DKIM_USERKEYS_DIR . "{$domain}");
        }
        if (file_exists(self::DKIM_SIGNING_TABLE)) {
            ShellService::exec("sed -i \"/\\^*\\@{$domain} /d\" " . self::DKIM_SIGNING_TABLE);
        }
        if (file_exists(self::DKIM_KEY_TABLE) && !empty($domain)) {
            ShellService::exec("sed -i '/^default._domainkey.{$domain} /d' " . self::DKIM_KEY_TABLE);
        }
        if (file_exists(self::DKIM_TRUSTED_HOSTS) && !empty($domain)) {
            ShellService::exec("sed -i '/^{$domain}[[:blank:]]*\$/d' " . self::DKIM_TRUSTED_HOSTS);
        }
    }

    public static function hookAddDkimNamed(string $domain): bool
    {
        $output = ShellService::exec("sh /scripts/hook_add_dkim_named " . escapeshellarg($domain) . " 2>&1");
        return !empty($output);
    }

    public static function addDkim(string $domain): bool
    {
        $output = ShellService::exec("sh /scripts/hook_add_dkim " . escapeshellarg($domain) . " 2>&1");
        return !empty($output);
    }

    public static function addSpf(string $domain, string $ip): bool
    {
        $zoneFile = self::ZONE_BASE . $domain . '.db';
        if (!file_exists($zoneFile)) return false;

        $content = file_get_contents($zoneFile);
        if (preg_match('/v=spf1/', $content)) {
            return false;
        }

        $spfRecord = "\n@   14400   IN  TXT \"v=spf1 +a +mx +ip4:{$ip} ~all\"\n";
        file_put_contents($zoneFile, $spfRecord, FILE_APPEND | LOCK_EX);

        self::updateSerial($domain);
        self::manageServices('reload', ['named']);
        return true;
    }

    public static function getDkimStatus(string $domain): array
    {
        $trustedHosts = (int) trim(ShellService::exec("grep -c " . escapeshellarg("^" . $domain) . " " . self::DKIM_TRUSTED_HOSTS . " 2>/dev/null") ?: '0');
        $keyTable = (int) trim(ShellService::exec("grep -c " . escapeshellarg("default._domainkey." . $domain) . " " . self::DKIM_KEY_TABLE . " 2>/dev/null") ?: '0');
        $signingTable = (int) trim(ShellService::exec("grep -c " . escapeshellarg($domain) . " " . self::DKIM_SIGNING_TABLE . " 2>/dev/null") ?: '0');

        return [
            'trusted_hosts' => $trustedHosts > 0,
            'key_table' => $keyTable > 0,
            'signing_table' => $signingTable > 0,
            'key_file' => file_exists(self::DKIM_KEYS_DIR . $domain . '/default.private'),
        ];
    }

    public static function getDkimKey(string $domain): string
    {
        return ShellService::readFile(self::DKIM_KEYS_DIR . $domain . '/default.txt');
    }

    public static function checkDkimRemote(string $domain): string
    {
        $output = ShellService::exec("dig TXT default._domainkey.{$domain} @8.8.8.8 +short 2>/dev/null");
        $output = str_replace('"', '', trim($output));
        $localKey = ShellService::readFile(self::DKIM_KEYS_DIR . $domain . '/default.txt');
        if (!empty($localKey)) {
            preg_match('/"v=DKIM1.*"/s', $localKey, $m);
            $localKeyValue = isset($m[0]) ? $m[0] : '';
            if ($output === $localKeyValue) {
                return 'active';
            }
        }
        return empty($output) ? 'not_found' : 'mismatch';
    }

    public static function checkSpfStatus(string $domain): bool
    {
        $zoneFile = self::ZONE_BASE . $domain . '.db';
        if (!file_exists($zoneFile)) return false;
        $output = ShellService::exec("grep 'v=spf1' {$zoneFile}");
        return !empty(trim($output));
    }

    public static function checkDmarcStatus(string $domain): bool
    {
        $zoneFile = self::ZONE_BASE . $domain . '.db';
        if (!file_exists($zoneFile)) return false;
        $output = ShellService::exec("grep 'v=DMARC1' {$zoneFile}");
        return !empty(trim($output));
    }

    public static function addDkimAll(): array
    {
        $results = [];
        $users = DB::connection('mysql')->table('user')->where('id', '!=', '')->get();
        foreach ($users as $user) {
            $domain = $user->domain;
            $results[$domain] = self::addDkim($domain);

            $domains = DB::connection('mysql')->table('domains')->where('user', $user->username)->get();
            foreach ($domains as $d) {
                $results[$d->domain] = self::addDkim($d->domain);
            }
        }
        return $results;
    }

    public static function listDkimDomains(): array
    {
        $results = [];
        $zones = self::listZones();
        foreach ($zones as $domain) {
            $dkim = self::getDkimStatus($domain);
            $results[] = [
                'domain' => $domain,
                'dkim_trusted' => $dkim['trusted_hosts'],
                'dkim_key' => $dkim['key_table'],
                'dkim_signing' => $dkim['signing_table'],
                'dkim_file' => $dkim['key_file'],
            ];
        }
        return $results;
    }

    public static function reloadZone(string $domain): string
    {
        return ShellService::exec("/usr/sbin/rndc reload " . escapeshellarg($domain) . " 2>&1");
    }

    protected static function manageServices(string $action, array $services): string
    {
        $output = '';
        foreach ($services as $service) {
            if (file_exists("/usr/lib/systemd/system/{$service}.service")) {
                $output .= ShellService::exec("systemctl {$action} {$service}.service 2>&1");
            } else {
                $output .= ShellService::exec("service {$service} {$action}");
            }
        }
        return $output;
    }

    protected static function externalHooks(string $name, string $action, string $domain): void
    {
        $data = ['name' => $name, 'action' => $action, 'domain' => $domain];
        if (file_exists('/scripts/External_Hooks')) {
            ShellService::exec("sh /scripts/External_Hooks " . escapeshellarg(json_encode($data)) . " 2>&1");
        }
    }

    protected static function slaveDnsManager(): ?object
    {
        if (!file_exists(self::SLAVE_DNS_CONF)) return null;
        $conf = json_decode(file_get_contents(self::SLAVE_DNS_CONF), true);
        if (!$conf) return null;
        return new DnsManagerClient(
            $conf['api_key'] ?? '',
            $conf['api_secret'] ?? '',
            $conf['base_url'] ?? ''
        );
    }

    public static function dnsManagerAddNewDomain(string $domain): ?string
    {
        if (!file_exists(self::SLAVE_DNS_CONF)) return null;
        $conf = json_decode(file_get_contents(self::SLAVE_DNS_CONF), true);
        $masterIp = $conf['master_ips'] ?? $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        if (empty($masterIp)) return null;

        $manager = self::slaveDnsManager();
        if (!$manager) return null;

        file_put_contents(self::DNS_SLAVE_LOG, date('Y-m-d H:i:s') . " Add Domain: {$domain}\n", FILE_APPEND);
        return $manager->addDomain($domain, $masterIp);
    }

    public static function dnsManagerRemoveDomain(string $domain): ?string
    {
        if (!file_exists(self::SLAVE_DNS_CONF)) return null;
        $manager = self::slaveDnsManager();
        if (!$manager) return null;

        file_put_contents(self::DNS_SLAVE_LOG, date('Y-m-d H:i:s') . " Remove Domain: {$domain}\n", FILE_APPEND);
        return $manager->removeDomain($domain);
    }

    public static function dnsManagerPushAll(): ?string
    {
        if (!file_exists(self::SLAVE_DNS_CONF)) return null;
        $conf = json_decode(file_get_contents(self::SLAVE_DNS_CONF), true);
        $masterIp = $conf['master_ips'] ?? $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
        $manager = self::slaveDnsManager();
        if (!$manager) return null;

        $domainArray = [];
        $users = DB::connection('mysql')->table('user')->where('id', '!=', '')->orderBy('username')->get();
        foreach ($users as $user) {
            $domains = DB::connection('mysql')->table('domains')->where('user', $user->username)->get();
            foreach ($domains as $d) {
                $domainArray[] = ['master' => $masterIp, 'domain' => $d->domain];
            }
            $domainArray[] = ['master' => $masterIp, 'domain' => $user->domain];
        }

        return $manager->addMultipleDomains($domainArray);
    }

    public static function dnsManagerGetStatus(): ?string
    {
        if (!file_exists(self::SLAVE_DNS_CONF)) return null;
        $manager = self::slaveDnsManager();
        if (!$manager) return null;

        $domainArray = [];
        $users = DB::connection('mysql')->table('user')->where('id', '!=', '')->orderBy('username')->get();
        foreach ($users as $user) {
            $domains = DB::connection('mysql')->table('domains')->where('user', $user->username)->get();
            foreach ($domains as $d) {
                $domainArray[] = ['domain' => $d->domain];
            }
            $domainArray[] = ['domain' => $user->domain];
        }

        return $manager->getDomainStatus($domainArray);
    }

    public static function getSlaveDnsConfig(): ?array
    {
        if (!file_exists(self::SLAVE_DNS_CONF)) return null;
        return json_decode(file_get_contents(self::SLAVE_DNS_CONF), true);
    }

    public static function setSlaveDnsConfig(array $config): bool
    {
        return ShellService::writeFile(self::SLAVE_DNS_CONF, json_encode($config, JSON_PRETTY_PRINT));
    }

    public static function getSlaveDnsStatus(): array
    {
        $conf = self::getSlaveDnsConfig();
        $output = ShellService::exec("systemctl is-active named 2>/dev/null");
        return [
            'active' => trim($output) === 'active',
            'config' => $conf,
            'master_ips' => $conf['master_ips'] ?? [],
        ];
    }

    public static function getRecordTypes(): array
    {
        return ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR'];
    }
}

class DnsManagerClient
{
    private string $key;
    private string $secret;
    private string $baseUrl;
    private string $hash;
    private string $requestTs;

    public function __construct(string $api_key, string $api_secret, string $base_url)
    {
        $this->key = $api_key;
        $this->secret = $api_secret;
        $this->baseUrl = $base_url;
        $ts = new \DateTime();
        $this->requestTs = $ts->format('c');
        $this->hash = hash('sha256', $this->key . $this->requestTs . $this->secret);
    }

    private function curlInit(string $endpoint): \CurlHandle
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'HASH-KEY: ' . $this->hash,
            'API-KEY: ' . $this->key,
            'REQUEST-TS: ' . $this->requestTs,
        ]);
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }

    public function getDomainList(): string
    {
        $ch = $this->curlInit('/api/domains.php');
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function addDomain(string $domain, string $master): string
    {
        $ch = $this->curlInit('/api/create_domain.php');
        $data = json_encode(['domain' => $domain, 'master' => $master]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function addMultipleDomains(array $domainArray): string
    {
        $ch = $this->curlInit('/api/create_domains.php');
        $data = json_encode($domainArray);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function getDomainStatus(array $domainArray): string
    {
        $ch = $this->curlInit('/api/domain_status.php');
        $data = json_encode($domainArray);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function removeDomain(string $domain): string
    {
        $ch = $this->curlInit('/api/delete_domain.php');
        $data = json_encode(['domain' => $domain]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function removeMultipleDomains(array $domains): string
    {
        $ch = $this->curlInit('/api/delete_domains.php');
        $data = json_encode(['domains' => $domains]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
