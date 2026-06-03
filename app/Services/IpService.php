<?php

namespace App\Services;

class IpService
{
    const IP_CONF_DIR = '/etc/ips/';
    const IPPOOL_CONF = '/etc/ippool.conf';
    const IPALIAS_DIR = '/etc/sysconfig/network-scripts/';
    const NAT_CONF = '/usr/local/openpanel/.conf/nat.conf';
    const IPNAT_JSON = '/usr/local/openpanel/.conf/ipnat.json';

    public static function getIpList(): array
    {
        $ips = [];
        $output = ShellService::exec('ip -4 addr show 2>/dev/null');
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+)\/(\d+)/', $line, $m)) {
                $ip = $m[1];
                $netmask = self::cidrToNetmask((int) $m[2]);
                $infoFile = self::IP_CONF_DIR . $ip;
                $info = file_exists($infoFile) ? parse_ini_file($infoFile) : [];
                $ips[] = [
                    'ip' => $ip,
                    'netmask' => $netmask,
                    'cidr' => (int) $m[2],
                    'interface' => $info['IFACE'] ?? '',
                    'main_server' => $info['MAIN_SERVER'] ?? '',
                    'is_shared' => $info['SHARED'] ?? '',
                    'owner' => $info['OWNER'] ?? '',
                ];
            }
        }
        return $ips;
    }

    public static function getIpDetails(string $ip): array
    {
        $infoFile = self::IP_CONF_DIR . $ip;
        $info = file_exists($infoFile) ? parse_ini_file($infoFile) : [];
        $users = ShellService::exec("grep -rl " . escapeshellarg($ip) . " /var/named/*.db 2>/dev/null | wc -l");
        return [
            'ip' => $ip,
            'info' => $info,
            'domains_using' => (int) $users,
            'reverse_dns' => ShellService::exec("dig -x " . escapeshellarg($ip) . " +short 2>/dev/null"),
        ];
    }

    public static function addIp(string $ip, string $netmask, string $interface, string $owner = 'root', string $dedicated = 'no'): bool
    {
        $cidr = self::netmaskToCidr($netmask);
        $ifFile = self::IPALIAS_DIR . "ifcfg-{$interface}:0";

        $content = "DEVICE={$interface}:0\nIPADDR={$ip}\nNETMASK={$netmask}\nONBOOT=yes\n";
        ShellService::writeFile($ifFile, $content);

        $dedicatedVal = ($dedicated === 'yes') ? 'yes' : 'no';
        $infoContent = "[IP]\nIPADDR={$ip}\nNETMASK={$netmask}\nIFACE={$interface}\nMAIN_SERVER=yes\nSHARED=no\nDEDICATED={$dedicatedVal}\nOWNER={$owner}\n";
        ShellService::writeFile(self::IP_CONF_DIR . $ip, $infoContent);

        ShellService::exec("ip addr add {$ip}/{$cidr} dev {$interface} 2>&1");
        return true;
    }

    public static function deleteIp(string $ip, string $interface = 'eth0'): bool
    {
        $ifDetails = self::getIpInterfaceDetails($ip);
        if (!empty($ifDetails['iface']) && $ifDetails['iface'] !== $interface) {
            $interface = $ifDetails['iface'];
        }

        ShellService::exec("ip addr del " . escapeshellarg($ip) . "/32 dev " . escapeshellarg($interface) . " 2>&1");
        ShellService::exec("rm -f " . escapeshellarg(self::IP_CONF_DIR . $ip));
        ShellService::exec("rm -f " . escapeshellarg(self::IPALIAS_DIR . "ifcfg-{$interface}:{$ip}"));
        self::deleteIpNatUser($ip);
        return true;
    }

    public static function setAsShared(string $ip): bool
    {
        $infoFile = self::IP_CONF_DIR . $ip;
        if (!file_exists($infoFile)) return false;
        $content = file_get_contents($infoFile);
        $content = preg_replace('/SHARED=.*/', 'SHARED=yes', $content);
        file_put_contents($infoFile, $content);
        return true;
    }

    public static function setAsDedicated(string $ip): bool
    {
        $infoFile = self::IP_CONF_DIR . $ip;
        if (!file_exists($infoFile)) return false;
        $content = file_get_contents($infoFile);
        $content = preg_replace('/SHARED=.*/', 'SHARED=no', $content);
        file_put_contents($infoFile, $content);
        return true;
    }

    public static function getIpInterfaceDetails(string $ip): array
    {
        $output = ShellService::exec("ip addr show | grep " . escapeshellarg($ip));
        $iface = '';
        if (preg_match('/(\w+)\s*$/m', trim($output), $m)) {
            $iface = trim($m[1]);
        }
        $infoFile = self::IP_CONF_DIR . $ip;
        $info = file_exists($infoFile) ? parse_ini_file($infoFile) : [];
        return [
            'iface' => $iface ?: ($info['IFACE'] ?? ''),
            'info' => $info,
        ];
    }

    public static function getIpPool(): array
    {
        if (!file_exists(self::IPPOOL_CONF)) return [];
        $content = ShellService::readFile(self::IPPOOL_CONF);
        return array_filter(explode("\n", $content));
    }

    public static function addToPool(string $ip): bool
    {
        return ShellService::addLineToFileIfMissing(self::IPPOOL_CONF, $ip);
    }

    public static function removeFromPool(string $ip): bool
    {
        return ShellService::deleteLineFromFile(self::IPPOOL_CONF, '/' . preg_quote($ip, '/') . '/');
    }

    public static function getNatConfig(): array
    {
        if (!file_exists(self::NAT_CONF)) return [];
        return json_decode(ShellService::readFile(self::NAT_CONF), true) ?? [];
    }

    public static function setNatConfig(array $config): bool
    {
        return ShellService::writeFile(self::NAT_CONF, json_encode($config, JSON_PRETTY_PRINT));
    }

    public static function networkingNat(): array
    {
        $nat = ['nat' => 'OFF', 'local_ip' => '', 'public_ip' => ''];
        if (file_exists(self::NAT_CONF)) {
            $content = trim(ShellService::exec("sed -n '/^{/,\$p' " . self::NAT_CONF));
            $conf = json_decode($content, true);
            if (is_array($conf)) {
                $nat['nat'] = $conf['nat'] ?? 'OFF';
                $nat['local_ip'] = $conf['local_ip'] ?? '';
                $nat['public_ip'] = $conf['public_ip'] ?? '';
            }
        }
        return $nat;
    }

    public static function natIp(string $ip): string|false
    {
        $natConf = self::NAT_CONF;
        if (!file_exists($natConf)) return false;

        $content = trim(ShellService::exec("sed -n '/^{/,\$p' " . $natConf));
        $nat = json_decode($content, true);
        if (!is_array($nat)) return false;

        if (isset($nat['nat']) && $nat['nat'] == 'ON' && !empty($nat['local_ip']) && !empty($nat['public_ip'])) {
            if ($ip === $nat['local_ip']) {
                return $nat['public_ip'];
            }
        }
        return false;
    }

    public static function validIpNat(string $ip): string|false
    {
        if (!file_exists(self::IPNAT_JSON)) return false;
        $content = trim(ShellService::exec("sed -n '/^{/,\$p' " . self::IPNAT_JSON));
        $ipnat = json_decode($content, true);
        if (!is_array($ipnat)) return false;

        if (isset($ipnat['local'][$ip]['natip'])) {
            return $ipnat['local'][$ip]['natip'];
        }
        if (isset($ipnat[$ip]['natip'])) {
            return $ipnat[$ip]['natip'];
        }
        return false;
    }

    public static function getIpIpnat(string $ip): string
    {
        $natIp = self::validIpNat($ip);
        return $natIp !== false ? $natIp : $ip;
    }

    public static function getIpnatConfig(): array
    {
        if (!file_exists(self::IPNAT_JSON)) return [];
        $content = trim(ShellService::exec("sed -n '/^{/,\$p' " . self::IPNAT_JSON));
        return json_decode($content, true) ?? [];
    }

    public static function setIpnatConfig(array $config): bool
    {
        return ShellService::writeFile(self::IPNAT_JSON, json_encode($config, JSON_PRETTY_PRINT));
    }

    public static function addIpNat(string $ip, string $natIp, string $eth): bool
    {
        $config = self::getIpnatConfig();
        $config[$ip] = ['natip' => $natIp, 'eth' => $eth];
        self::setIpnatConfig($config);
        return true;
    }

    public static function deleteIpNatUser(string $username): bool
    {
        $user = \Illuminate\Support\Facades\DB::connection('mysql')->table('user')->where('username', $username)->first();
        if (!$user) return false;
        $ip = $user->ip_address;

        $config = self::getIpnatConfig();
        if (isset($config[$ip])) {
            unset($config[$ip]);
            self::setIpnatConfig($config);
        }
        return true;
    }

    public static function isLocalIpAddress(string $ip, bool $includeLoopback = false): bool
    {
        $filter = $includeLoopback ? '' : '! loopback';
        $output = ShellService::exec("ip -4 addr show {$filter} 2>/dev/null | grep 'inet '");
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
                if ($m[1] === $ip) return true;
            }
        }
        return false;
    }

    public static function getInetIps(): array
    {
        $ips = [];
        if (!is_dir(self::IP_CONF_DIR)) return $ips;
        $files = ShellService::exec("ls " . self::IP_CONF_DIR . " 2>/dev/null");
        foreach (explode("\n", $files) as $file) {
            $file = trim($file);
            if (!empty($file) && preg_match('/^\d+\.\d+\.\d+\.\d+$/', $file)) {
                $ips[] = $file;
            }
        }
        return $ips;
    }

    public static function networkingFirstSetup(): bool
    {
        $nat = self::networkingNat();
        if ($nat['nat'] !== 'ON' || empty($nat['local_ip']) || empty($nat['public_ip'])) return false;

        $localIp = $nat['local_ip'];
        $publicIp = $nat['public_ip'];
        $iface = 'lo:0';

        ShellService::exec("ifconfig lo:0 {$publicIp} netmask 255.255.255.255 up 2>/dev/null");
        ShellService::exec("ip addr add {$publicIp}/32 dev lo 2>/dev/null");

        $confContent = ShellService::readFile('/usr/local/openpanel/.conf/openpanel.conf');
        $confContent = str_replace("server_ips={$localIp}", "server_ips={$publicIp}", $confContent);
        ShellService::writeFile('/usr/local/openpanel/.conf/openpanel.conf', $confContent);

        return true;
    }

    public static function getNetworkConfig(): string
    {
        return ShellService::exec('ip addr show 2>/dev/null');
    }

    public static function getRoutingTable(): string
    {
        return ShellService::exec('ip route show 2>/dev/null');
    }

    public static function getDnsResolvers(): array
    {
        $content = ShellService::readFile('/etc/resolv.conf');
        $servers = [];
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^nameserver\s+(\S+)/', $line, $m)) {
                $servers[] = $m[1];
            }
        }
        return $servers;
    }

    public static function setDnsResolvers(array $servers): bool
    {
        $content = "# Generated by OpenPanel\n";
        foreach ($servers as $server) {
            $content .= "nameserver {$server}\n";
        }
        return ShellService::writeFile('/etc/resolv.conf', $content);
    }

    public static function cidrToNetmask(int $cidr): string
    {
        return long2ip(-1 << (32 - $cidr));
    }

    public static function netmaskToCidr(string $netmask): int
    {
        return 32 - log((ip2long($netmask) ^ ip2long('255.255.255.255')) + 1, 2);
    }
}
