<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use App\Models\Domain;
use App\Models\DnsZone;
use App\Models\MysqlDatabase;
use App\Models\EmailAccount;
use App\Models\Service;
use App\Models\Notification;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_accounts' => UserAccount::count(),
            'active_accounts' => UserAccount::where('suspended', 'no')->count(),
            'suspended_accounts' => UserAccount::where('suspended', 'yes')->count(),
            'total_domains' => Domain::count(),
            'ssl_domains' => Domain::where('ssl_enabled', true)->count(),
            'dns_zones' => DnsZone::count(),
            'databases' => MysqlDatabase::count(),
            'email_accounts' => EmailAccount::count(),
            'total_services' => Service::count(),
            'running_services' => Service::where('status', 'running')->count(),
            'expired_ssl' => SslCertificate::where('expires_at', '<', now())->count(),
            'expiring_ssl' => SslCertificate::whereBetween('expires_at', [now(), now()->addDays(30)])->count(),
        ];

        $services = Service::orderBy('display_name')->get();

        $recentNotifications = Notification::where('is_read', false)
            ->latest()
            ->limit(10)
            ->get();

        $diskUsage = $this->getDiskUsage();
        $memoryUsage = $this->getMemoryUsage();
        $swapUsage = $this->getSwapUsage();
        $cpuLoad = $this->getCpuLoad();

        return view('dashboard', compact(
            'stats', 'services', 'recentNotifications',
            'diskUsage', 'memoryUsage', 'swapUsage', 'cpuLoad'
        ));
    }

    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => $percent,
            'color' => self::diskColor($percent),
            'total_fmt' => self::formatBytes($total),
            'used_fmt' => self::formatBytes($used),
            'free_fmt' => self::formatBytes($free),
        ];
    }

    private function getMemoryUsage(): array
    {
        $meminfo = self::parseMeminfo();
        $total = $meminfo['MemTotal'] ?? 0;
        $free = $meminfo['MemFree'] ?? 0;
        $buffers = $meminfo['Buffers'] ?? 0;
        $cached = $meminfo['Cached'] ?? 0;
        $used = $total - $free - $buffers - $cached;
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        return [
            'total' => $total,
            'used' => max(0, $used),
            'free' => $free + $buffers + $cached,
            'percent' => $percent,
            'color' => self::memoryColor($percent),
            'total_fmt' => self::formatBytes($total * 1024),
            'used_fmt' => self::formatBytes(max(0, $used) * 1024),
            'free_fmt' => self::formatBytes(($free + $buffers + $cached) * 1024),
        ];
    }

    private function getSwapUsage(): array
    {
        $meminfo = self::parseMeminfo();
        $total = $meminfo['SwapTotal'] ?? 0;
        $free = $meminfo['SwapFree'] ?? 0;
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => $percent,
            'color' => self::swapColor($percent),
            'total_fmt' => self::formatBytes($total * 1024),
            'used_fmt' => self::formatBytes($used * 1024),
            'free_fmt' => self::formatBytes($free * 1024),
        ];
    }

    private function getCpuLoad(): array
    {
        $load = sys_getloadavg();
        $cpuCount = (int) trim(shell_exec('nproc') ?: '1');
        $loadPercent = $cpuCount > 0 ? round(($load[0] ?? 0) / $cpuCount * 100, 1) : 0;
        return [
            '1min' => $load[0] ?? 0,
            '5min' => $load[1] ?? 0,
            '15min' => $load[2] ?? 0,
            'cores' => $cpuCount,
            'percent' => min(100, $loadPercent),
            'color' => self::cpuColor($loadPercent),
        ];
    }

    private static function parseMeminfo(): array
    {
        $meminfo = [];
        if (file_exists('/proc/meminfo')) {
            $content = file_get_contents('/proc/meminfo');
            foreach (explode("\n", $content) as $line) {
                if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                    $meminfo[$m[1]] = (int) $m[2];
                }
            }
        }
        return $meminfo;
    }

    private static function memoryColor(float $percent): string
    {
        if ($percent <= 50) return 'green';
        if ($percent <= 70) return 'blue';
        if ($percent <= 80) return 'yellow';
        if ($percent <= 90) return 'orange';
        return 'red';
    }

    private static function swapColor(float $percent): string
    {
        if ($percent <= 20) return 'green';
        if ($percent <= 50) return 'blue';
        if ($percent <= 70) return 'yellow';
        return 'red';
    }

    private static function diskColor(float $percent): string
    {
        if ($percent <= 75) return 'green';
        if ($percent <= 85) return 'blue';
        if ($percent <= 90) return 'yellow';
        if ($percent <= 95) return 'orange';
        return 'red';
    }

    private static function cpuColor(float $percent): string
    {
        if ($percent <= 25) return 'green';
        if ($percent <= 50) return 'blue';
        if ($percent <= 75) return 'yellow';
        if ($percent <= 90) return 'orange';
        return 'red';
    }

    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
    }
}
