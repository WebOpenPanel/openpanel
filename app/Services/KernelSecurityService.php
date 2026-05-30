<?php

namespace App\Services;

class KernelSecurityService
{
    public static function getSecurityScore(): array
    {
        $score = 100;
        $issues = [];

        $modules = self::getLoadedModules();
        $dangerous = ['cramfs', 'freevxfs', 'hfs', 'hfsplus', 'jffs2', 'squashfs', 'udf', 'dccp', 'sctp', 'rds', 'tipc'];
        foreach ($dangerous as $mod) {
            if (in_array($mod, $modules)) {
                $score -= 5;
                $issues[] = "Dangerous module loaded: {$mod}";
            }
        }

        $sysctl = self::getSysctlValues();
        $checks = [
            ['key' => 'net.ipv4.conf.all.accept_redirects', 'expected' => '0', 'label' => 'ICMP redirects accepted'],
            ['key' => 'net.ipv4.conf.all.accept_source_route', 'expected' => '0', 'label' => 'Source routing accepted'],
            ['key' => 'net.ipv4.conf.all.log_martians', 'expected' => '1', 'label' => 'Martian packets not logged'],
            ['key' => 'net.ipv4.conf.all.rp_filter', 'expected' => '1', 'label' => 'Reverse path filtering disabled'],
            ['key' => 'net.ipv4.conf.all.send_redirects', 'expected' => '0', 'label' => 'ICMP redirects sent'],
            ['key' => 'net.ipv4.icmp_echo_ignore_broadcasts', 'expected' => '1', 'label' => 'Broadcast ICMP not ignored'],
            ['key' => 'net.ipv4.tcp_syncookies', 'expected' => '1', 'label' => 'SYN cookies disabled'],
            ['key' => 'kernel.randomize_va_space', 'expected' => '2', 'label' => 'ASLR not fully enabled'],
            ['key' => 'kernel.exec-shield', 'expected' => '1', 'label' => 'Exec-shield disabled'],
        ];

        foreach ($checks as $check) {
            $current = $sysctl[$check['key']] ?? 'unknown';
            if ($current !== $check['expected']) {
                $score -= 5;
                $issues[] = "{$check['label']}: {$check['key']} = {$current} (expected {$check['expected']})";
            }
        }

        $score = max(0, $score);

        return ['score' => $score, 'issues' => $issues, 'total_checks' => count($checks) + count($dangerous)];
    }

    public static function getLoadedModules(): array
    {
        $output = ShellService::exec('lsmod | awk \'NR>1 {print $1}\'');
        return array_filter(explode("\n", trim($output)));
    }

    public static function getSysctlValues(): array
    {
        $output = ShellService::exec('sysctl -a 2>/dev/null');
        $values = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(\S+)\s*=\s*(.+)$/', trim($line), $m)) {
                $values[$m[1]] = trim($m[2]);
            }
        }
        return $values;
    }

    public static function blacklistModule(string $module): array
    {
        $sanitized = preg_replace('/[^a-z0-9_]/', '', strtolower($module));
        $confPath = '/etc/modprobe.d/openpanel-blacklist.conf';
        $content = file_exists($confPath) ? file_get_contents($confPath) : "# OpenPanel kernel module blacklist\n";
        if (stripos($content, "blacklist {$sanitized}") !== false) {
            return ['success' => false, 'message' => "Module {$sanitized} is already blacklisted."];
        }
        $content .= "blacklist {$sanitized}\ninstall {$sanitized} /bin/true\n";
        file_put_contents($confPath, $content);
        ShellService::exec("modprobe -r {$sanitized} 2>/dev/null");
        return ['success' => true, 'message' => "Module {$sanitized} blacklisted."];
    }

    public static function unblacklistModule(string $module): array
    {
        $sanitized = preg_replace('/[^a-z0-9_]/', '', strtolower($module));
        $confPath = '/etc/modprobe.d/openpanel-blacklist.conf';
        if (!file_exists($confPath)) {
            return ['success' => false, 'message' => 'Blacklist file not found.'];
        }
        $content = file_get_contents($confPath);
        $content = preg_replace("/^blacklist\s+{$sanitized}\s*$/m", '', $content);
        $content = preg_replace("/^install\s+{$sanitized}\s+.+$/m", '', $content);
        file_put_contents($confPath, $content);
        return ['success' => true, 'message' => "Module {$sanitized} removed from blacklist."];
    }

    public static function applySysctlHardening(): array
    {
        $hardening = [
            'net.ipv4.conf.all.accept_redirects' => '0',
            'net.ipv4.conf.default.accept_redirects' => '0',
            'net.ipv4.conf.all.accept_source_route' => '0',
            'net.ipv4.conf.default.accept_source_route' => '0',
            'net.ipv4.conf.all.log_martians' => '1',
            'net.ipv4.conf.all.rp_filter' => '1',
            'net.ipv4.conf.all.send_redirects' => '0',
            'net.ipv4.conf.default.send_redirects' => '0',
            'net.ipv4.icmp_echo_ignore_broadcasts' => '1',
            'net.ipv4.tcp_syncookies' => '1',
            'net.ipv6.conf.all.accept_redirects' => '0',
            'net.ipv6.conf.default.accept_redirects' => '0',
            'kernel.randomize_va_space' => '2',
        ];

        $confPath = '/etc/sysctl.d/99-openpanel-hardening.conf';
        $content = "# OpenPanel kernel hardening\n";
        foreach ($hardening as $key => $value) {
            $content .= "{$key} = {$value}\n";
        }
        file_put_contents($confPath, $content);
        ShellService::exec('sysctl --system 2>&1');

        return ['success' => true, 'message' => 'Kernel hardening applied.'];
    }

    public static function getBlacklistedModules(): array
    {
        $confPath = '/etc/modprobe.d/openpanel-blacklist.conf';
        if (!file_exists($confPath)) {
            return [];
        }
        $modules = [];
        foreach (explode("\n", file_get_contents($confPath)) as $line) {
            if (preg_match('/^blacklist\s+(\S+)/', trim($line), $m)) {
                $modules[] = $m[1];
            }
        }
        return $modules;
    }
}
