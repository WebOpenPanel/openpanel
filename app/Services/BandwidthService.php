<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BandwidthService
{
    protected static string $bandwidthDir = '/usr/local/openpanel/.conf/bandwidth';
    protected static string $bandwidthLog = '/var/log/openpanel/bandwidth.log';

    public static function getUsage(string $user = '', string $period = 'today'): array
    {
        if ($user) {
            return self::getUserUsage($user, $period);
        }
        return self::getAllUsage($period);
    }

    public static function getAllUsage(string $period = 'today'): array
    {
        $usernames = DB::table('user')->pluck('username')->toArray();
        $usage = [];
        foreach ($usernames as $username) {
            $u = self::getUserUsage($username, $period);
            if ($u['total_bytes'] > 0) {
                $usage[] = array_merge(['username' => $username], $u);
            }
        }
        usort($usage, fn($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);
        return $usage;
    }

    public static function getUserUsage(string $user, string $period = 'today'): array
    {
        $homeDir = '/home/' . $user;
        $usage = ['in_bytes' => 0, 'out_bytes' => 0, 'total_bytes' => 0, 'connections' => 0];

        $vnstatOutput = ShellService::exec("vnstat --json 2>/dev/null");
        if ($vnstatOutput) {
            $data = json_decode($vnstatOutput, true);
            if ($data) {
                $usage = self::parseVnstatData($data, $period);
            }
        }

        if ($usage['total_bytes'] === 0) {
            $usage = self::estimateFromLogs($user, $period);
        }

        return $usage;
    }

    public static function getHistory(string $user, int $days = 30): array
    {
        $history = [];
        $bandwidthFile = self::$bandwidthDir . '/' . $user . '.json';

        if (file_exists($bandwidthFile)) {
            $data = json_decode(file_get_contents($bandwidthFile), true) ?: [];
            $cutoff = strtotime("-{$days} days");
            foreach ($data as $entry) {
                if (($entry['timestamp'] ?? 0) >= $cutoff) {
                    $history[] = $entry;
                }
            }
        }

        return $history;
    }

    public static function getTopUsers(string $period = 'month', int $limit = 10): array
    {
        $usage = self::getAllUsage($period);
        return array_slice($usage, 0, $limit);
    }

    public static function getInterfaces(): array
    {
        $output = ShellService::exec("ip -o link show | awk -F': ' '{print $2}' | grep -v lo");
        return array_filter(explode("\n", trim($output)));
    }

    public static function getInterfaceUsage(string $interface = 'eth0'): array
    {
        $output = ShellService::exec("vnstat -i {$interface} --json 2>/dev/null");
        if ($output) {
            $data = json_decode($output, true);
            if ($data) {
                return self::parseVnstatData($data, 'today');
            }
        }

        $proc = file_get_contents("/proc/net/dev");
        if (preg_match("/{$interface}:\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/", $proc, $m)) {
            return ['in_bytes' => (int)$m[1], 'out_bytes' => (int)$m[2], 'total_bytes' => (int)$m[1] + (int)$m[2]];
        }

        return ['in_bytes' => 0, 'out_bytes' => 0, 'total_bytes' => 0];
    }

    protected static function parseVnstatData(array $data, string $period): array
    {
        $in = 0;
        $out = 0;

        $iface = $data['interfaces'][0] ?? null;
        if (!$iface) {
            return ['in_bytes' => 0, 'out_bytes' => 0, 'total_bytes' => 0, 'connections' => 0];
        }

        $traffic = $iface['traffic'] ?? [];

        if ($period === 'today' && !empty($traffic['day'])) {
            $today = $traffic['day'][0] ?? [];
            $in = ($today['rx'] ?? 0) * 1024;
            $out = ($today['tx'] ?? 0) * 1024;
        } elseif ($period === 'month' && !empty($traffic['month'])) {
            $month = $traffic['month'][0] ?? [];
            $in = ($month['rx'] ?? 0) * 1024;
            $out = ($month['tx'] ?? 0) * 1024;
        } elseif ($period === 'total' && !empty($traffic['total'])) {
            $total = $traffic['total'];
            $in = ($total['rx'] ?? 0) * 1024;
            $out = ($total['tx'] ?? 0) * 1024;
        }

        return ['in_bytes' => $in, 'out_bytes' => $out, 'total_bytes' => $in + $out, 'connections' => 0];
    }

    protected static function estimateFromLogs(string $user, string $period): array
    {
        $in = 0;
        $out = 0;
        $connections = 0;

        $accessLog = "/var/log/nginx/{$user}_access.log";
        if (file_exists($accessLog)) {
            $date = $period === 'today' ? date('d/M/Y') : '';
            $grep = $date ? ShellService::exec("grep '{$date}' {$accessLog} | awk '{sum += $10} END {print sum}'") : ShellService::exec("awk '{sum += $10} END {print sum}' {$accessLog}");
            $out = (int)trim($grep);
            $connections = (int)ShellService::exec("wc -l < {$accessLog}");
        }

        return ['in_bytes' => $in, 'out_bytes' => $out, 'total_bytes' => $in + $out, 'connections' => $connections];
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int)floor(log($bytes, 1024));
        return round($bytes / (1024 ** $i), $precision) . ' ' . $units[$i];
    }
}
