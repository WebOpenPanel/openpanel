<?php

namespace App\Services;

class SecurityService
{
    const CSF_CONF = '/etc/csf/csf.conf';
    const CSF_ALLOW = '/etc/csf/csf.allow';
    const CSF_DENY = '/etc/csf/csf.deny';
    const CSF_IGNORE = '/etc/csf/csf.ignore';
    const MODSEC_CONF = '/etc/httpd/conf/modsecurity.conf';
    const MODSEC_RULES = '/etc/httpd/conf.d/mod_security.conf';
    const CGROUPS_CONF = '/etc/cgconfig.conf';
    const CGROUPS_RULES = '/etc/cgrules.conf';
    const CGROUPS_CONF_DIR = '/etc/cgconfig.d/';
    const CGROUPS_CONF_PATH = '/usr/local/openpanel/.conf/cgroups_configuration.ini';
    const MALDET_LOG_DIR = '/var/log/maldetect/';
    const RKHUNTER_LOG_DIR = '/var/log/rkhunter/';
    const LYNIS_LOG_DIR = '/var/log/lynis/';

    // CSF Firewall
    public static function csfStatus(): string
    {
        return ShellService::exec('/usr/sbin/csf -l 2>/dev/null');
    }

    public static function csfEnable(): string
    {
        return ShellService::exec('/usr/sbin/csf -e 2>&1');
    }

    public static function csfDisable(): string
    {
        return ShellService::exec('/usr/sbin/csf -x 2>&1');
    }

    public static function csfRestart(): string
    {
        return ShellService::exec('/usr/sbin/csf -r 2>&1');
    }

    public static function csfQuickRestart(): string
    {
        return ShellService::exec('/usr/sbin/csf -q 2>&1');
    }

    public static function csfFlushAll(): string
    {
        $output = ShellService::exec('/usr/sbin/csf -df 2>&1');
        $output .= "\n" . ShellService::exec('/usr/sbin/csf -tf 2>&1');
        return $output;
    }

    public static function csfTest(): string
    {
        return ShellService::exec('/usr/local/csf/bin/csftest.pl 2>&1');
    }

    public static function csfUpdate(): string
    {
        return ShellService::exec('/usr/sbin/csf -u 2>&1 && /usr/sbin/csf -v 2>&1');
    }

    public static function csfAllowIp(string $ip, string $comment = ''): string
    {
        return ShellService::exec('/usr/sbin/csf -a ' . escapeshellarg($ip) . ' ' . escapeshellarg($comment) . ' 2>&1');
    }

    public static function csfDenyIp(string $ip, string $comment = '', bool $permanent = false): string
    {
        $cmd = '/usr/sbin/csf -d ' . escapeshellarg($ip) . ' ' . escapeshellarg($comment);
        if ($permanent) {
            $cmd .= ' do not delete';
        }
        return ShellService::exec($cmd . ' 2>&1');
    }

    public static function csfUnblockIp(string $ip): string
    {
        $output = ShellService::exec('/usr/sbin/csf -dr ' . escapeshellarg($ip) . ' 2>&1');
        $output .= "\n" . ShellService::exec('/usr/sbin/csf -tr ' . escapeshellarg($ip) . ' 2>&1');
        return $output;
    }

    public static function csfGetConf(): string
    {
        return ShellService::readFile(self::CSF_CONF);
    }

    public static function csfSaveConf(string $content): bool
    {
        return ShellService::writeFile(self::CSF_CONF, $content);
    }

    public static function csfGetAllowList(): array
    {
        return array_filter(explode("\n", ShellService::readFile(self::CSF_ALLOW)));
    }

    public static function csfGetDenyList(): array
    {
        return array_filter(explode("\n", ShellService::readFile(self::CSF_DENY)));
    }

    public static function lfdStatus(): string
    {
        return ShellService::exec('/etc/init.d/lfd status 2>&1');
    }

    public static function lfdRestart(): string
    {
        return ShellService::exec('/etc/init.d/lfd restart 2>&1');
    }

    // Iptables
    public static function iptablesList(): string
    {
        return ShellService::exec('iptables -L -n -v --line-numbers 2>/dev/null');
    }

    public static function iptablesListRaw(): string
    {
        return ShellService::exec('iptables-save 2>/dev/null');
    }

    public static function iptablesFlush(): string
    {
        return ShellService::exec('iptables -F 2>&1');
    }

    public static function iptablesSave(): string
    {
        return ShellService::exec('iptables-save > /etc/sysconfig/iptables 2>&1');
    }

    // ModSecurity
    public static function getModSecurityStatus(): array
    {
        $installed = ShellService::exec("rpm -q mod_security 2>/dev/null || dpkg -l libapache2-mod-security2 2>/dev/null | grep -c ii");
        $enabled = ShellService::lineExistsInFile(self::MODSEC_RULES, '/SecRuleEngine\s+On/');
        return [
            'installed' => !empty(trim($installed)) && strpos($installed, 'not installed') === false,
            'enabled' => $enabled,
        ];
    }

    public static function modSecurityEnable(): bool
    {
        ShellService::replaceInFile(self::MODSEC_RULES, 'SecRuleEngine DetectionOnly', 'SecRuleEngine On');
        ServerService::serviceAction('restart', 'httpd');
        return true;
    }

    public static function modSecurityDisable(): bool
    {
        ShellService::replaceInFile(self::MODSEC_RULES, 'SecRuleEngine On', 'SecRuleEngine DetectionOnly');
        ServerService::serviceAction('restart', 'httpd');
        return true;
    }

    public static function getModSecurityConf(): string
    {
        return ShellService::readFile(self::MODSEC_CONF);
    }

    public static function saveModSecurityConf(string $content): bool
    {
        ShellService::writeFile(self::MODSEC_CONF, $content);
        ServerService::serviceAction('restart', 'httpd');
        return true;
    }

    public static function getModSecurityRules(): string
    {
        return ShellService::readFile(self::MODSEC_RULES);
    }

    public static function saveModSecurityRules(string $content): bool
    {
        ShellService::writeFile(self::MODSEC_RULES, $content);
        ServerService::serviceAction('restart', 'httpd');
        return true;
    }

    // Maldet
    public static function maldetIsInstalled(): bool
    {
        return file_exists('/usr/local/sbin/maldet') || file_exists('/usr/local/sbin/lmd');
    }

    public static function maldetInstall(): string
    {
        return ShellService::exec('sh /scripts/install_maldet 2>&1');
    }

    public static function maldetUninstall(): string
    {
        $output = ShellService::exec('rm -rf /usr/local/maldetect* /etc/cron.d/maldet_pub /etc/cron.daily/maldet /usr/local/sbin/maldet /usr/local/sbin/lmd 2>&1');
        $output .= ShellService::exec('rm -f /var/lib/clamav/rfxn.* /var/lib/clamav/lmd.user.* 2>&1');
        if (file_exists('/usr/lib/systemd/system/maldet.service')) {
            ShellService::exec('systemctl disable maldet.service && systemctl stop maldet.service && rm -f /usr/lib/systemd/system/maldet.service && systemctl daemon-reload');
        }
        return $output;
    }

    public static function maldetUpdate(): string
    {
        return ShellService::exec('maldet -u 2>&1');
    }

    public static function maldetScan(string $path): string
    {
        return ShellService::exec('maldet -a ' . escapeshellarg($path) . ' 2>&1');
    }

    public static function maldetScanUser(string $username): string
    {
        return self::maldetScan('/home/' . $username);
    }

    public static function maldetGetScans(): array
    {
        $scans = [];
        $sessDir = self::MALDET_LOG_DIR . 'sess/';
        if (!is_dir($sessDir)) return $scans;
        foreach (ShellService::dirList($sessDir) as $file) {
            if ($file === '.' || $file === '..' || $file === 'session.last') continue;
            if (strpos($file, 'clean') !== false || strpos($file, 'hits') !== false) continue;
            $scanId = str_replace('session.', '', $file);
            $scans[] = [
                'id' => $scanId,
                'file' => $file,
                'time' => date('Y-m-d H:i:s', filemtime($sessDir . $file)),
                'content' => ShellService::readFile($sessDir . $file),
            ];
        }
        usort($scans, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
        return $scans;
    }

    public static function maldetAction(string $action, string $scanId): string
    {
        return ShellService::exec('maldet --' . $action . ' ' . escapeshellarg($scanId) . ' 2>&1');
    }

    // RKHunter
    public static function rkhunterIsInstalled(): bool
    {
        return !empty(ShellService::exec('which rkhunter 2>/dev/null'));
    }

    public static function rkhunterInstall(): string
    {
        return ShellService::exec('yum -y install rkhunter --enablerepo=epel 2>&1');
    }

    public static function rkhunterUninstall(): string
    {
        return ShellService::exec('yum -y remove rkhunter 2>&1');
    }

    public static function rkhunterUpdate(): string
    {
        return ShellService::exec('rkhunter --update 2>&1');
    }

    public static function rkhunterScan(): string
    {
        $logDir = self::RKHUNTER_LOG_DIR;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'rkhunter_' . date('Y-m-d-H-i-s') . '.log';
        return ShellService::exec('rkhunter --check --sk --nocolors --logfile ' . escapeshellarg($logFile) . ' 2>&1');
    }

    public static function rkhunterGetScans(): array
    {
        $scans = [];
        $logDir = self::RKHUNTER_LOG_DIR;
        if (!is_dir($logDir)) return $scans;
        foreach (ShellService::dirList($logDir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $scans[] = [
                'file' => $file,
                'time' => date('Y-m-d H:i:s', filemtime($logDir . $file)),
                'content' => ShellService::readFile($logDir . $file),
            ];
        }
        usort($scans, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
        return $scans;
    }

    // Lynis
    public static function lynisIsInstalled(): bool
    {
        return !empty(ShellService::exec('which lynis 2>/dev/null'));
    }

    public static function lynisInstall(): string
    {
        return ShellService::exec('yum -y install lynis --enablerepo=epel 2>&1');
    }

    public static function lynisUninstall(): string
    {
        return ShellService::exec('yum -y remove lynis 2>&1');
    }

    public static function lynisScan(): string
    {
        $logDir = self::LYNIS_LOG_DIR;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d-H-i-s');
        $logFile = $logDir . 'lynis_' . $timestamp . '.log';
        $reportFile = $logDir . 'lynis_report_' . $timestamp . '.dat';
        return ShellService::exec('lynis audit system --no-colors --logfile ' . escapeshellarg($logFile) . ' --report-file ' . escapeshellarg($reportFile) . ' 2>&1');
    }

    public static function lynisGetScans(): array
    {
        $scans = [];
        $logDir = self::LYNIS_LOG_DIR;
        if (!is_dir($logDir)) return $scans;
        foreach (ShellService::dirList($logDir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $scans[] = [
                'file' => $file,
                'time' => date('Y-m-d H:i:s', filemtime($logDir . $file)),
                'content' => ShellService::readFile($logDir . $file),
            ];
        }
        usort($scans, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
        return $scans;
    }

    // Symlink scan
    public static function symlinkScan(string $path = '/home'): string
    {
        return ShellService::exec('find ' . escapeshellarg($path) . ' -type l -ls 2>/dev/null | head -100');
    }

    public static function symlinkScanUser(string $username): string
    {
        return self::symlinkScan('/home/' . $username);
    }

    // Login security
    public static function getFailedLogins(int $hours = 24): array
    {
        $output = ShellService::exec("lastb --time-format iso 2>/dev/null | head -100");
        $logins = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 3) {
                $logins[] = [
                    'user' => $parts[0],
                    'ip' => $parts[2] ?? '',
                    'time' => $parts[3] ?? '',
                ];
            }
        }
        return $logins;
    }

    public static function getSuccessfulLogins(int $lines = 50): string
    {
        return ShellService::exec("last -n {$lines} 2>/dev/null");
    }

    public static function getLoggedInUsers(): string
    {
        return ShellService::exec('w 2>/dev/null');
    }

    // CloudLinux / Cgroups
    public static function cgroupsIsInstalled(): bool
    {
        return file_exists('/usr/sbin/cgconfigparser') || file_exists('/usr/sbin/cgclear');
    }

    public static function cgroupsGetStatus(): array
    {
        $cgconfig = ShellService::exec('systemctl is-active cgconfig 2>/dev/null');
        $cgred = ShellService::exec('systemctl is-active cgred 2>/dev/null');
        return [
            'cgconfig' => trim($cgconfig) === 'active',
            'cgred' => trim($cgred) === 'active',
        ];
    }

    public static function cgroupsRestart(): string
    {
        $output = ShellService::exec('systemctl restart cgconfig 2>&1');
        $output .= "\n" . ShellService::exec('systemctl restart cgred 2>&1');
        return $output;
    }

    public static function cgroupsGetConf(): string
    {
        return ShellService::readFile(self::CGROUPS_CONF);
    }

    public static function cgroupsSaveConf(string $content): bool
    {
        ShellService::writeFile(self::CGROUPS_CONF, $content);
        self::cgroupsRestart();
        return true;
    }

    public static function cgroupsGetUserConf(string $username): string
    {
        return ShellService::readFile(self::CGROUPS_CONF_DIR . $username . '.conf');
    }

    public static function cgroupsSetUserLimit(string $username, array $limits): bool
    {
        $conf = '';
        if (isset($limits['cpu'])) {
            $conf .= "cpu.cfs_quota_us = " . $limits['cpu'] . "\n";
        }
        if (isset($limits['memory'])) {
            $conf .= "memory.limit_in_bytes = " . $limits['memory'] . "\n";
        }
        if (isset($limits['io_read'])) {
            $conf .= "blkio.throttle.read_bps_device = " . $limits['io_read'] . "\n";
        }
        if (isset($limits['io_write'])) {
            $conf .= "blkio.throttle.write_bps_device = " . $limits['io_write'] . "\n";
        }
        return ShellService::writeFile(self::CGROUPS_CONF_DIR . $username . '.conf', $conf);
    }

    // Shell access
    public static function getShells(): array
    {
        $content = ShellService::readFile('/etc/shells');
        return array_filter(explode("\n", $content));
    }

    public static function setUserShell(string $username, string $shell): bool
    {
        ShellService::exec("usermod -s " . escapeshellarg($shell) . " " . escapeshellarg($username));
        return true;
    }

    public static function getUserShell(string $username): string
    {
        return ShellService::exec("getent passwd " . escapeshellarg($username) . " | cut -d: -f7");
    }

    // Ulimits
    public static function getUlimits(): string
    {
        return ShellService::readFile('/etc/security/limits.conf');
    }

    public static function saveUlimits(string $content): bool
    {
        return ShellService::writeFile('/etc/security/limits.conf', $content);
    }

    // User monitoring
    public static function getUserProcesses(string $username): string
    {
        return ShellService::exec("ps aux | grep " . escapeshellarg($username) . " | grep -v grep 2>/dev/null");
    }

    public static function getOpenFiles(string $username): string
    {
        return ShellService::exec("lsof -u " . escapeshellarg($username) . " 2>/dev/null | head -50");
    }

    public static function getUserCpuUsage(): array
    {
        $output = ShellService::exec("ps aux --sort=-%cpu | awk 'NR>1{users[$1]+=$3}END{for(u in users)if(users[u]>0)print u,users[u]}' | sort -k2 -rn | head -20");
        $usage = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $usage[] = ['user' => $parts[0], 'cpu' => (float) $parts[1]];
            }
        }
        return $usage;
    }

    // Kernel
    public static function getKernelInfo(): array
    {
        $version = ShellService::exec('uname -r');
        $full = ShellService::exec('uname -a');
        return ['version' => $version, 'full' => $full];
    }

    public static function kernelUpdate(): string
    {
        return ShellService::exec('yum -y update kernel 2>&1');
    }

    // ClamAV (ported from ClamEngineTrait.php)
    const CLAM_LOG_DIR = '/var/log/clamav-engine/';
    const CLAM_QUARantine_DIR = '/var/quarantine_clamav/';
    const CLAM_EXCLUDE_FILE = '/usr/local/openpanel/.conf/clamexclude.inf';

    public static function clamAvIsInstalled(): bool
    {
        return !empty(ShellService::exec('which clamscan 2>/dev/null'));
    }

    public static function clamAvInstall(): string
    {
        $output = ShellService::exec('yum -y install clamav clamav-db clamd 2>&1');
        $output .= "\n" . ShellService::exec('freshclam 2>&1');
        ShellService::exec("mkdir -p " . self::CLAM_LOG_DIR);
        ShellService::exec("mkdir -p " . self::CLAM_QUARantine_DIR);
        return $output;
    }

    public static function clamAvStatus(): string
    {
        if (!self::clamAvIsInstalled()) return 'not installed';
        $update = ShellService::exec('freshclam --version 2>/dev/null');
        return trim($update) ?: 'installed';
    }

    public static function clamAvUpdate(): string
    {
        return ShellService::exec('freshclam 2>&1');
    }

    public static function clamAvScan(string $path, string $type = 'scan', int $maxFiles = 0, bool $quarantineInfected = true): array
    {
        $scanId = 'cl_' . date('mdHis') . '_' . rand(10, 99);
        $logFile = self::CLAM_LOG_DIR . $scanId . '.log';
        $pidFile = self::CLAM_LOG_DIR . $scanId . '.pid';

        $excludeOpt = '';
        if (file_exists(self::CLAM_EXCLUDE_FILE)) {
            $excludeOpt = ' --exclude-dir=' . str_replace("\n", ' --exclude-dir=', trim(ShellService::readFile(self::CLAM_EXCLUDE_FILE)));
        }

        $maxFilesOpt = $maxFiles > 0 ? " --max-filesize={$maxFiles}M --max-scansize={$maxFiles}M" : '';
        $quarantineOpt = $quarantineInfected ? ' --move=' . self::CLAM_QUARantine_DIR : '';

        $typeOpt = match ($type) {
            'quick' => ' --infected --recursive',
            'full' => ' --infected --recursive --allmatch --scan-archive --scan-html --scan-pe --scan-elf --scan-ole2 --scan-pdf --scan-swf',
            default => ' --infected --recursive',
        };

        ShellService::execBackground("clamscan{$typeOpt}{$excludeOpt}{$maxFilesOpt}{$quarantineOpt} " . escapeshellarg($path) . " > " . escapeshellarg($logFile) . " 2>&1 & echo $! > " . escapeshellarg($pidFile));

        return ['scanId' => $scanId, 'logFile' => $logFile, 'pidFile' => $pidFile];
    }

    public static function clamAvScanState(string $scanId): array
    {
        $logFile = self::CLAM_LOG_DIR . $scanId . '.log';
        $pidFile = self::CLAM_LOG_DIR . $scanId . '.pid';

        if (!file_exists($logFile)) return ['status' => 'not_found'];

        $running = false;
        if (file_exists($pidFile)) {
            $pid = trim(ShellService::readFile($pidFile));
            $running = ShellService::exec("ps -p {$pid} >/dev/null 2>&1 && echo yes || echo no");
            $running = trim($running) === 'yes';
        }

        $content = ShellService::exec("tail -n 50 " . escapeshellarg($logFile));
        return [
            'status' => $running ? 'running' : 'completed',
            'output' => $content,
            'scanId' => $scanId,
        ];
    }

    public static function clamAvCancelScan(string $scanId): bool
    {
        $pidFile = self::CLAM_LOG_DIR . $scanId . '.pid';
        if (!file_exists($pidFile)) return false;
        $pid = trim(ShellService::readFile($pidFile));
        ShellService::exec("kill -9 {$pid} 2>/dev/null");
        return true;
    }

    public static function clamAvGetHistory(): array
    {
        $historyFile = self::CLAM_LOG_DIR . 'history.log';
        if (!file_exists($historyFile)) return [];
        $lines = ShellService::exec("tail -n 50 " . escapeshellarg($historyFile));
        $history = [];
        foreach (array_filter(explode("\n", $lines)) as $line) {
            if (preg_match('/\[(.*?)\]\s+(.*)/', $line, $m)) {
                $history[] = ['time' => $m[1], 'message' => $m[2]];
            }
        }
        return array_reverse($history);
    }

    public static function clamAvSetExclude(array $dirs): bool
    {
        return ShellService::writeFile(self::CLAM_EXCLUDE_FILE, implode("\n", $dirs));
    }

    public static function clamAvGetExclude(): array
    {
        if (!file_exists(self::CLAM_EXCLUDE_FILE)) return [];
        return array_filter(explode("\n", ShellService::readFile(self::CLAM_EXCLUDE_FILE)));
    }

    // ModSecurity Incidents (ported from ModSecEngineTrait.php)
    public static function modSecListIncidents(int $limit = 200): array
    {
        $auditLog = '/usr/local/apache/logs/modsec_audit.log';
        if (!file_exists($auditLog)) return [];
        $output = ShellService::exec("tail -n {$limit} " . escapeshellarg($auditLog) . " 2>/dev/null");
        $incidents = [];
        foreach (array_filter(explode("\n", $output)) as $line) {
            if (preg_match('/\[id\s+"(\d+)"\].*\[msg\s+"(.*?)"\].*\[client\s+"(.*?)"\]/', $line, $m)) {
                $incidents[] = [
                    'rule_id' => $m[1],
                    'message' => $m[2],
                    'client_ip' => $m[3],
                    'raw' => $line,
                ];
            }
        }
        return $incidents;
    }

    public static function modSecGetRuleById(string $ruleId): ?string
    {
        $output = ShellService::exec("grep -rn '{$ruleId}' /etc/modsecurity.d/ /etc/httpd/conf.d/mod_security/ 2>/dev/null | head -1");
        return empty(trim($output)) ? null : $output;
    }

    public static function modSecGetRuleFileContent(string $ruleId): ?string
    {
        $output = ShellService::exec("grep -rn '{$ruleId}' /etc/modsecurity.d/ /etc/httpd/conf.d/mod_security/ 2>/dev/null | head -1 | cut -d: -f1");
        $filePath = trim($output);
        if (empty($filePath) || !file_exists($filePath)) return null;
        return ShellService::readFile($filePath);
    }

    public static function modSecAddWhitelist(string $domain, string $ruleId): bool
    {
        $confFile = "/usr/local/apache/conf.d/{$domain}.conf";
        $ruleLine = "    SecRuleRemoveById {$ruleId}";
        if (file_exists($confFile) && ShellService::lineExistsInFile($confFile, "/SecRuleRemoveById\s+{$ruleId}/")) {
            return true;
        }
        ShellService::exec("echo '" . addslashes($ruleLine) . "' >> " . escapeshellarg($confFile));
        ServerService::serviceAction('restart', 'httpd');
        return true;
    }

    public static function modSecDeleteWhitelist(string $domain, string $ruleId): bool
    {
        $confFile = "/usr/local/apache/conf.d/{$domain}.conf";
        if (!file_exists($confFile)) return false;
        ShellService::exec("sed -i '/SecRuleRemoveById\s*{$ruleId}/d' " . escapeshellarg($confFile));
        ServerService::serviceAction('restart', 'httpd');
        return true;
    }

    // PHP Defender / Snuffleupagus (ported from PHPDefenderMainTrait.php)
    const DEFENDER_INI_DIR = '/opt/alt/php-snuffleupagus/usr/etc/php.d.all/';
    const DEFENDER_SP_DIR = '/opt/sp_rules/';

    public static function defenderIsInstalled(): bool
    {
        return file_exists('/opt/alt/php-snuffleupagus/usr/lib64/php/modules/snuffleupagus.so');
    }

    public static function defenderGetVersions(): array
    {
        $versions = [];
        $dir = '/opt/alt/';
        if (!is_dir($dir)) return $versions;
        foreach (ShellService::dirList($dir) as $entry) {
            if (preg_match('/^php(\d+)$/', $entry, $m)) {
                $iniPath = $dir . $entry . '/usr/etc/php.d.all/zzz_snuffleupagus.ini';
                $versions[] = [
                    'version' => $m[1],
                    'installed' => file_exists($iniPath),
                    'enabled' => file_exists($iniPath) && !preg_match('/^;/', trim(ShellService::exec("head -1 " . escapeshellarg($iniPath)))),
                ];
            }
        }
        return $versions;
    }

    public static function defenderGetIniContent(string $version): string
    {
        $iniPath = "/opt/alt/php{$version}/usr/etc/php.d.all/zzz_snuffleupagus.ini";
        return file_exists($iniPath) ? ShellService::readFile($iniPath) : '';
    }

    public static function defenderSaveIniContent(string $version, string $content): bool
    {
        $iniPath = "/opt/alt/php{$version}/usr/etc/php.d.all/zzz_snuffleupagus.ini";
        return ShellService::writeFile($iniPath, $content);
    }

    public static function defenderGetRules(): array
    {
        $rules = [];
        if (!is_dir(self::DEFENDER_SP_DIR)) return $rules;
        foreach (ShellService::dirList(self::DEFENDER_SP_DIR) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'rules') {
                $rules[] = [
                    'file' => $file,
                    'content' => ShellService::readFile(self::DEFENDER_SP_DIR . $file),
                ];
            }
        }
        return $rules;
    }

    public static function defenderGetRuleContent(string $ruleFile): string
    {
        return ShellService::readFile(self::DEFENDER_SP_DIR . $ruleFile);
    }

    public static function defenderSaveRuleContent(string $ruleFile, string $content): bool
    {
        return ShellService::writeFile(self::DEFENDER_SP_DIR . $ruleFile, $content);
    }

    public static function defenderGetIncidents(): array
    {
        $logDir = '/var/log/snuffleupagus/';
        if (!is_dir($logDir)) return [];
        $incidents = [];
        $logFile = $logDir . 'log';
        if (!file_exists($logFile)) return [];
        $lines = ShellService::exec("tail -n 100 " . escapeshellarg($logFile));
        foreach (array_filter(explode("\n", $lines)) as $line) {
            if (preg_match('/\[(.*?)\]\s+(.*)/', $line, $m)) {
                $incidents[] = ['time' => $m[1], 'message' => $m[2]];
            }
        }
        return array_reverse($incidents);
    }

    // Ban attacker IP via CSF (from IPSHandler::banipattckr)
    public static function banAttackerIp(string $ip, int $tempban = 0): string
    {
        if ($tempban > 0) {
            return ShellService::exec('/usr/sbin/csf -td ' . escapeshellarg($ip) . ' 2>&1');
        }
        return ShellService::exec('/usr/sbin/csf -d ' . escapeshellarg($ip) . ' ModSecurity attacker 2>&1');
    }
}
