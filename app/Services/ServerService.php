<?php

namespace App\Services;

class ServerService
{
    public static function getHostname(): string
    {
        return trim(ShellService::exec('hostname -f 2>/dev/null') ?: gethostname());
    }

    public static function setHostname(string $hostname): array
    {
        $old = self::getHostname();
        ShellService::exec("hostnamectl set-hostname " . escapeshellarg($hostname));
        ShellService::replaceInFile('/etc/hostname', $old, $hostname);
        ShellService::replaceInFile('/etc/hosts', $old, $hostname);
        return ['success' => true, 'old' => $old, 'new' => $hostname];
    }

    public static function getServerTime(): string
    {
        return ShellService::exec('date "+%Y-%m-%d %H:%M:%S %Z"');
    }

    public static function setTimezone(string $timezone): bool
    {
        ShellService::exec("timedatectl set-timezone " . escapeshellarg($timezone));
        return true;
    }

    public static function getTimezones(): array
    {
        $output = ShellService::exec('timedatectl list-timezones 2>/dev/null');
        return array_filter(explode("\n", $output));
    }

    public static function setDate(string $date, string $time): bool
    {
        ShellService::exec("timedatectl set-time " . escapeshellarg($date . ' ' . $time));
        return true;
    }

    public static function reboot(): void
    {
        ShellService::execBackground('reboot');
    }

    public static function shutdown(): void
    {
        ShellService::execBackground('poweroff');
    }

    public static function getUptime(): string
    {
        return ShellService::exec('uptime -p 2>/dev/null') ?: ShellService::exec('uptime');
    }

    public static function getLoadAvg(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return ['1min' => 0, '5min' => 0, '15min' => 0];
        }
        $load = sys_getloadavg();
        return ['1min' => $load[0] ?? 0, '5min' => $load[1] ?? 0, '15min' => $load[2] ?? 0];
    }

    public static function getCpuInfo(): array
    {
        $output = ShellService::exec('cat /proc/cpuinfo 2>/dev/null');
        $cpus = [];
        $current = [];
        foreach (explode("\n", $output) as $line) {
            if (empty(trim($line))) {
                if (!empty($current)) {
                    $cpus[] = $current;
                    $current = [];
                }
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $current[trim($key)] = trim($value);
        }
        if (!empty($current)) {
            $cpus[] = $current;
        }
        return $cpus;
    }

    public static function getCpuUsage(): float
    {
        $output = ShellService::exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}'");
        return (float) $output;
    }

    public static function getMemoryInfo(): array
    {
        $output = ShellService::exec('free -b 2>/dev/null');
        $lines = explode("\n", $output);
        $mem = [];
        foreach ($lines as $line) {
            if (preg_match('/^Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $m)) {
                $mem = ['total' => (int)$m[1], 'used' => (int)$m[2], 'free' => (int)$m[3]];
            }
            if (preg_match('/^Swap:\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $m)) {
                $mem['swap_total'] = (int)$m[1];
                $mem['swap_used'] = (int)$m[2];
                $mem['swap_free'] = (int)$m[3];
            }
        }
        return $mem;
    }

    public static function getDiskUsage(): array
    {
        $output = ShellService::exec('df -B1 2>/dev/null');
        $lines = explode("\n", $output);
        $disks = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6 && !empty($parts[0])) {
                $disks[] = [
                    'filesystem' => $parts[0],
                    'size' => (int)$parts[1],
                    'used' => (int)$parts[2],
                    'available' => (int)$parts[3],
                    'percent' => (int) str_replace('%', '', $parts[4]),
                    'mount' => $parts[5],
                ];
            }
        }
        return $disks;
    }

    public static function getDiskQuota(string $username): array
    {
        $output = ShellService::exec("repquota -a 2>/dev/null | grep " . escapeshellarg($username));
        $parts = preg_split('/\s+/', $output);
        return [
            'used' => $parts[2] ?? 0,
            'soft' => $parts[3] ?? 0,
            'hard' => $parts[4] ?? 0,
        ];
    }

    public static function setDiskQuota(string $username, int $softMb, int $hardMb): bool
    {
        ShellService::exec("setquota -u " . escapeshellarg($username) . " " . ($softMb * 1024) . " " . ($hardMb * 1024) . " 0 0 /");
        return true;
    }

    public static function getProcessList(): array
    {
        $output = ShellService::exec('ps aux --sort=-%cpu 2>/dev/null');
        $lines = explode("\n", trim($output));
        $processes = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) continue;
            $parts = preg_split('/\s+/', $line, 11);
            if (count($parts) >= 11) {
                $processes[] = [
                    'user' => $parts[0], 'pid' => $parts[1], 'cpu' => $parts[2],
                    'mem' => $parts[3], 'vsz' => $parts[4], 'rss' => $parts[5],
                    'tty' => $parts[6], 'stat' => $parts[7], 'start' => $parts[8],
                    'time' => $parts[9], 'command' => $parts[10],
                ];
            }
        }
        return $processes;
    }

    public static function killProcess(int $pid, string $signal = '9'): bool
    {
        ShellService::exec("kill -{$signal} {$pid}");
        return true;
    }

    public static function getNetstat(): string
    {
        return ShellService::exec('ss -tulnp 2>/dev/null || netstat -tulnp 2>/dev/null');
    }

    public static function getBandwidth(string $interface = 'eth0'): array
    {
        $output = ShellService::exec("cat /proc/net/dev 2>/dev/null");
        foreach (explode("\n", $output) as $line) {
            if (strpos($line, $interface) !== false) {
                $parts = preg_split('/\s+/', trim($line));
                return [
                    'rx_bytes' => (int)($parts[1] ?? 0),
                    'tx_bytes' => (int)($parts[9] ?? 0),
                    'rx_formatted' => ShellService::formatBytes((int)($parts[1] ?? 0)),
                    'tx_formatted' => ShellService::formatBytes((int)($parts[9] ?? 0)),
                ];
            }
        }
        return ['rx_bytes' => 0, 'tx_bytes' => 0, 'rx_formatted' => '0 B', 'tx_formatted' => '0 B'];
    }

    public static function getNetworkInterfaces(): array
    {
        $output = ShellService::exec('ip -j addr show 2>/dev/null');
        $interfaces = json_decode($output, true);
        if (!is_array($interfaces)) {
            $output = ShellService::exec('ip addr show 2>/dev/null');
            return ['raw' => $output];
        }
        return $interfaces;
    }

    public static function getSshKeys(): array
    {
        $keys = [];
        $keyDir = '/root/.ssh';
        if (is_dir($keyDir)) {
            foreach (ShellService::dirList($keyDir) as $file) {
                if (preg_match('/\.pub$/', $file)) {
                    $keys[] = ['name' => $file, 'content' => ShellService::readFile("$keyDir/$file")];
                }
            }
        }
        return $keys;
    }

    public static function generateSshKey(string $type = 'rsa', int $bits = 4096, string $comment = ''): array
    {
        $keyPath = '/root/.ssh/id_' . $type;
        $comment = $comment ?: 'root@' . self::getHostname();
        ShellService::exec("ssh-keygen -t {$type} -b {$bits} -f {$keyPath} -N '' -C " . escapeshellarg($comment));
        return [
            'public' => ShellService::readFile($keyPath . '.pub'),
            'private' => ShellService::readFile($keyPath),
        ];
    }

    public static function changeRootPassword(string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        ShellService::exec("echo 'root:" . escapeshellarg($newPassword) . "' | chpasswd");
        return true;
    }

    public static function getYumPackages(string $search = ''): array
    {
        $cmd = $search
            ? "yum list installed 2>/dev/null | grep -i " . escapeshellarg($search)
            : "yum list installed 2>/dev/null";
        $output = ShellService::exec($cmd);
        $packages = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 3) {
                $packages[] = ['name' => $parts[0], 'version' => $parts[1], 'repo' => $parts[2]];
            }
        }
        return $packages;
    }

    public static function yumInstall(string $package): string
    {
        return ShellService::exec("yum install -y " . escapeshellarg($package) . " 2>&1");
    }

    public static function yumRemove(string $package): string
    {
        return ShellService::exec("yum remove -y " . escapeshellarg($package) . " 2>&1");
    }

    public static function yumUpdate(string $package = ''): string
    {
        $cmd = $package ? "yum update -y " . escapeshellarg($package) : "yum update -y";
        return ShellService::exec($cmd . " 2>&1");
    }

    public static function getTopStats(): string
    {
        return ShellService::exec('top -bn1 -o %CPU | head -30');
    }

    public static function getSysStat(): string
    {
        return ShellService::exec('sar -u 1 1 2>/dev/null || echo "sysstat not installed"');
    }

    public static function getLiveMonitor(): string
    {
        return ShellService::exec('vmstat 1 3 2>/dev/null');
    }

    public static function runShellCommand(string $command): string
    {
        return ShellService::exec($command . ' 2>&1');
    }

    public static function getServiceList(): array
    {
        $output = ShellService::exec('systemctl list-units --type=service --all --no-pager 2>/dev/null');
        $services = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.*)/', $line, $m)) {
                $services[] = [
                    'unit' => $m[1], 'load' => $m[2], 'active' => $m[3],
                    'sub' => $m[4], 'description' => $m[5],
                ];
            }
        }
        return $services;
    }

    public static function getStartupServices(): array
    {
        $output = ShellService::exec('systemctl list-unit-files --type=service --no-pager 2>/dev/null');
        $services = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(\S+)\s+(\S+)/', $line, $m)) {
                $services[] = ['unit' => $m[1], 'state' => $m[2]];
            }
        }
        return $services;
    }

    public static function serviceAction(string $action, string $service): string
    {
        $allowed = ['start', 'stop', 'restart', 'reload', 'status', 'enable', 'disable'];
        if (!in_array($action, $allowed)) {
            return 'Invalid action';
        }
        return self::manageServices($action, [$service]);
    }

    public static function manageServices(string $action, array $serviceNames): string
    {
        $output = '';
        $logFile = '/var/log/openpanel/services_action.log';
        $timestamp = date('Y-m-d H:i:s');

        foreach ($serviceNames as $service_name) {
            if (file_exists("/usr/lib/systemd/system/{$service_name}.service")) {
                $result = ShellService::exec("systemctl {$action} {$service_name} 2>&1");
            } else {
                $result = ShellService::exec("service {$service_name} {$action} 2>&1");
            }
            $output .= $result;
            ShellService::exec("echo '[{$timestamp}] Service: {$service_name} Action: {$action} Status: {$result}' >> {$logFile}");
        }
        return $output;
    }

    public static function manageServicesStartup(string $action, array $serviceNames): string
    {
        $output = '';
        foreach ($serviceNames as $service_name) {
            if (file_exists("/usr/lib/systemd/system/{$service_name}.service")) {
                $systemctlAction = ($action === 'on') ? 'enable' : 'disable';
                $output .= ShellService::exec("systemctl {$systemctlAction} {$service_name} 2>&1");
            } else {
                $chkconfigAction = ($action === 'on') ? 'on' : 'off';
                $output .= ShellService::exec("chkconfig --level 2345 {$service_name} {$chkconfigAction} 2>&1");
            }
        }
        return $output;
    }

    public static function activeWebServerAction(string $action): string
    {
        $webServerConf = '/usr/local/openpanel/.conf/web_server.conf';
        if (!file_exists($webServerConf)) return 'Web server config not found';

        $webServerType = trim(file_get_contents($webServerConf));
        $services = [];

        switch ($webServerType) {
            case '1':
                $services = ['httpd'];
                break;
            case '2':
                $services = ['nginx', 'httpd'];
                break;
            case '4':
                $services = ['varnish', 'httpd'];
                break;
        }

        return self::manageServices($action, $services);
    }

    public static function webServersActiveAction(string $action): string
    {
        $webServersConf = '/usr/local/openpanel/.conf/web_servers.conf';
        if (!file_exists($webServersConf)) return 'Web servers config not found';

        $config = json_decode(file_get_contents($webServersConf), true);
        if (!$config) return 'Invalid web servers config';

        $output = '';
        $services = [];

        if (isset($config['nginx'])) $services[] = 'nginx';
        if (isset($config['varnish'])) $services[] = 'varnish';
        if (isset($config['apache-main']) || isset($config['apache-additional'])) $services[] = 'httpd';

        if (!empty($services)) {
            $output = self::manageServices($action, $services);
        }

        if (isset($config['php-fpm'])) {
            $output .= self::phpFpmServiceAction($action);
        }

        return $output;
    }

    protected static function phpFpmServiceAction(string $action): string
    {
        $output = '';
        $altDir = '/opt/alt/';
        if (!is_dir($altDir)) return '';

        $dirs = ShellService::exec("cd {$altDir}; ls -d php-fpm* 2>/dev/null");
        if (empty(trim($dirs))) return '';

        foreach (explode("\n", $dirs) as $dir) {
            $dir = trim($dir);
            if (empty($dir)) continue;
            if (file_exists("{$altDir}{$dir}/usr/sbin/php-fpm")) {
                $output .= self::manageServices($action, [$dir]);
            }
        }
        return $output;
    }

    public static function chkConfigAction(string $action, string $service): string
    {
        return ShellService::exec("chkconfig {$action} " . escapeshellarg($service) . " 2>&1");
    }

    public static function getMonitStatus(): string
    {
        return ShellService::exec('monit status 2>/dev/null || echo "monit not installed"');
    }

    public static function sendShellCommand(string $user, string $command): string
    {
        return ShellService::exec("su - " . escapeshellarg($user) . " -c " . escapeshellarg($command) . " 2>&1");
    }

    public static function screenList(): string
    {
        return ShellService::exec('screen -ls 2>/dev/null || echo "screen not installed"');
    }

    public static function screenCreate(string $name, string $command): string
    {
        return ShellService::exec("screen -dmS " . escapeshellarg($name) . " " . escapeshellarg($command) . " 2>&1");
    }

    const QUOTA_PART_CONF = '/usr/local/openpanel/.conf/quota_part.conf';
    const QUOTA_TYPE_CONF = '/usr/local/openpanel/.conf/quota_type.conf';

    public static function getQuotaPart(): string
    {
        if (file_exists(self::QUOTA_PART_CONF)) {
            $part = trim(file_get_contents(self::QUOTA_PART_CONF));
            return empty($part) ? '/' : $part;
        }
        return '/';
    }

    public static function getQuotaType(): string
    {
        if (file_exists(self::QUOTA_TYPE_CONF)) {
            $type = trim(file_get_contents(self::QUOTA_TYPE_CONF));
            return empty($type) ? 'vfsv0' : $type;
        }
        return 'vfsv0';
    }

    public static function quotaGetUser(string $user): array
    {
        $part = self::getQuotaPart();
        $type = self::getQuotaType();
        $output = ShellService::exec("repquota -u -F {$type} " . escapeshellarg($part) . " 2>/dev/null | grep " . escapeshellarg($user));
        $soft = 0;
        $hard = 0;
        $used = 0;
        if (preg_match('/\+\s+(\d+)\s+(\d+)\s+(\d+)/', $output, $m)) {
            $used = (int) $m[1];
            $soft = (int) $m[2];
            $hard = (int) $m[3];
        } elseif (preg_match('/^\s+\S+\s+(\d+)\s+(\d+)\s+(\d+)/m', $output, $m)) {
            $used = (int) $m[1];
            $soft = (int) $m[2];
            $hard = (int) $m[3];
        }
        return ['soft' => $soft, 'hard' => $hard, 'used' => $used];
    }

    public static function quotaSet(string $user, int $soft, int $hard): string
    {
        $part = self::getQuotaPart();
        $type = self::getQuotaType();
        $softStr = $soft > 0 ? $soft . 'M' : '0';
        $hardStr = $hard > 0 ? $hard . 'M' : '0';
        return ShellService::exec("setquota -u -F {$type} " . escapeshellarg($user) . " {$softStr} {$hardStr} 0 0 {$part} 2>&1");
    }

    public static function quotaSetDisabled(string $user): string
    {
        $part = self::getQuotaPart();
        $type = self::getQuotaType();
        return ShellService::exec("setquota -u -F {$type} " . escapeshellarg($user) . " 0 0 0 0 {$part} 2>&1");
    }

    public static function quotaSetAllUsers(int $soft, int $hard): string
    {
        $part = self::getQuotaPart();
        $type = self::getQuotaType();
        $softStr = $soft > 0 ? $soft . 'M' : '0';
        $hardStr = $hard > 0 ? $hard . 'M' : '0';
        $users = \Illuminate\Support\Facades\DB::connection('openpanel')->table('user')->where('id', '!=', '')->get();
        $output = '';
        foreach ($users as $user) {
            $output .= ShellService::exec("setquota -u -F {$type} " . escapeshellarg($user->username) . " {$softStr} {$hardStr} 0 0 {$part} 2>&1");
        }
        return $output;
    }

    public static function userSetLimits(string $user, array $limits): bool
    {
        $defaultLimits = [
            'nproc' => '65535',
            'nofile' => '65535',
            'sigpending' => '65535',
            'msgqueue' => '65535',
            'memlock' => '65535',
            'locks' => '65535',
        ];
        $limits = array_merge($defaultLimits, $limits);

        $securityLimits = "# Created by OpenPanel on " . date('Y-m-d H:i:s') . "\n";
        $securityLimits .= "# /etc/security/limits.conf for user {$user}\n";
        $securityLimits .= "#\n";
        $securityLimits .= "#<domain>      <type>  <item>         <value>\n";
        $securityLimits .= "#\n";
        $securityLimits .= "{$user} soft nproc {$limits['nproc']}\n";
        $securityLimits .= "{$user} hard nproc {$limits['nproc']}\n";
        $securityLimits .= "{$user} soft nofile {$limits['nofile']}\n";
        $securityLimits .= "{$user} hard nofile {$limits['nofile']}\n";
        $securityLimits .= "{$user} soft sigpending {$limits['sigpending']}\n";
        $securityLimits .= "{$user} hard sigpending {$limits['sigpending']}\n";
        $securityLimits .= "{$user} soft msgqueue {$limits['msgqueue']}\n";
        $securityLimits .= "{$user} hard msgqueue {$limits['msgqueue']}\n";
        $securityLimits .= "{$user} soft memlock {$limits['memlock']}\n";
        $securityLimits .= "{$user} hard memlock {$limits['memlock']}\n";
        $securityLimits .= "{$user} soft locks {$limits['locks']}\n";
        $securityLimits .= "{$user} hard locks {$limits['locks']}\n";

        $limitDir = '/etc/security/limits.d';
        ShellService::exec("mkdir -p {$limitDir}");
        ShellService::writeFile("{$limitDir}/{$user}.limits", $securityLimits);

        $homeDir = "/home/{$user}";
        if (is_dir($homeDir)) {
            ShellService::exec("chown -R {$user}:{$user} {$homeDir} 2>/dev/null");
        }

        return true;
    }

    public static function userGetLimits(string $user): array
    {
        $limitFile = "/etc/security/limits.d/{$user}.limits";
        if (!file_exists($limitFile)) {
            return [
                'nproc' => '65535',
                'nofile' => '65535',
                'sigpending' => '65535',
                'msgqueue' => '65535',
                'memlock' => '65535',
                'locks' => '65535',
            ];
        }
        $content = file_get_contents($limitFile);
        $limits = [];
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\S+\s+soft\s+(\S+)\s+(\S+)/', $line, $m)) {
                $limits[$m[1]] = $m[2];
            }
        }
        return $limits;
    }

    public static function userSetCgroups(string $user, int $cpu, int $mem): bool
    {
        $cgroupConf = '/etc/cgconfig.conf';
        $cgroupRules = "/etc/cgrules.conf";

        ShellService::exec("groupadd {$user} 2>/dev/null");
        ShellService::exec("usermod -a -G {$user} {$user} 2>/dev/null");

        $cgroupEntry = "group {$user} {\n";
        $cgroupEntry .= "    cpu {\n";
        $cgroupEntry .= "        cpu.cfs_quota_us = " . ($cpu * 1000) . ";\n";
        $cgroupEntry .= "    }\n";
        $cgroupEntry .= "    memory {\n";
        $cgroupEntry .= "        memory.limit_in_bytes = " . ($mem * 1024 * 1024) . ";\n";
        $cgroupEntry .= "    }\n";
        $cgroupEntry .= "}\n";

        $existing = file_exists($cgroupConf) ? file_get_contents($cgroupConf) : '';
        if (strpos($existing, "group {$user}") === false) {
            file_put_contents($cgroupConf, $cgroupEntry, FILE_APPEND | LOCK_EX);
        }

        $ruleEntry = "{$user}\tcpu,memory\t{$user}/\n";
        ShellService::addLineToFileIfMissing($cgroupRules, $ruleEntry, '/' . preg_quote($user, '/') . '/');

        return true;
    }

    public static function hostnameSetNew(string $hostname): bool
    {
        $hostname = strtolower($hostname);
        $hostname = preg_replace('/[^a-z0-9\-\.]/', '', $hostname);
        if (empty($hostname)) return false;

        $currentHostname = self::getHostname();
        $ip = ShellService::exec("hostname -I | awk '{print $1}'");

        ShellService::exec("hostnamectl set-hostname " . escapeshellarg($hostname));
        ShellService::replaceInFile('/etc/hostname', $currentHostname, $hostname);

        $hostsContent = ShellService::readFile('/etc/hosts');
        $hostsContent = str_replace($currentHostname, $hostname, $hostsContent);
        ShellService::writeFile('/etc/hosts', $hostsContent);

        $webpanelConf = '/usr/local/openpanel/conf/webpanel.conf';
        if (file_exists($webpanelConf)) {
            ShellService::replaceInFile($webpanelConf, $currentHostname, $hostname);
        }

        $postfixMain = '/etc/postfix/main.cf';
        if (file_exists($postfixMain)) {
            ShellService::replaceInFile($postfixMain, "myhostname = {$currentHostname}", "myhostname = {$hostname}");
        }

        ShellService::exec("hostnamectl set-hostname " . escapeshellarg($hostname) . " 2>&1");
        self::manageServices('restart', ['openpanel-srv', 'postfix', 'dovecot']);

        return true;
    }

    public static function updateCheck(): array
    {
        $updateConf = '/usr/local/openpanel/.conf/openpanel_update.conf';
        if (!file_exists($updateConf)) {
            return ['auto_update' => false, 'last_check' => null];
        }
        $content = trim(ShellService::exec("sed -n '/^{/,\$p' " . $updateConf));
        $conf = json_decode($content, true);
        return [
            'auto_update' => ($conf['auto_update'] ?? 'no') === 'yes',
            'last_check' => $conf['last_check'] ?? null,
        ];
    }

    public static function packageUpdateAll(): string
    {
        return ShellService::exec("/scripts/update_all_users_packages 2>&1");
    }

    public static function packageUpdateUser(string $user, string $package): string
    {
        return ShellService::exec("/scripts/update_user_package " . escapeshellarg($user) . " " . escapeshellarg($package) . " 2>&1");
    }
}
