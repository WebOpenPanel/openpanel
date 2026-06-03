<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use App\Services\ShellService;
use Illuminate\Support\Facades\DB;
use Illuminate\Process\Factory as ProcessFactory;

class DashboardController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $accounts = $this->getAccountStats();
        $system = $this->getSystemStats();

        return view('dashboard', compact('accounts', 'system'));
    }

    protected function getAccountStats(): array
    {
        $total = 0;
        $active = 0;
        $suspended = 0;

        try {
            $rows = DB::connection('mysql')->table('accounts')->get();
            $total = $rows->count();
            $active = $rows->where('status', 'active')->count();
            $suspended = $rows->where('status', 'suspended')->count();
        } catch (\Exception $e) {
            $result = $this->process()->run("awk -F: '(\$3 >= 1000 && \$3 < 65534) {print \$1}' /etc/passwd | wc -l");
            $total = (int) trim($result->output());
            $active = $total;
        }

        return compact('total', 'active', 'suspended');
    }

    protected function getSystemStats(): array
    {
        $disk = $this->getDiskUsage();
        $memory = $this->getMemoryUsage();
        $swap = $this->getSwapUsage();
        $cpu = $this->getCpuLoad();
        $uptime = trim($this->process()->run('uptime -p')->output() ?: 'unknown');
        $hostname = trim($this->process()->run('hostname -f')->output() ?: 'unknown');
        $os = trim($this->process()->run("cat /etc/os-release | grep PRETTY_NAME | cut -d'\"' -f2")->output() ?: 'Linux');
        $kernel = trim($this->process()->run('uname -r')->output() ?: '');

        return compact('disk', 'memory', 'swap', 'cpu', 'uptime', 'hostname', 'os', 'kernel');
    }

    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percent' => $percent,
            'color' => $this->colorThreshold($percent, [75, 85, 90, 95]),
        ];
    }

    private function getMemoryUsage(): array
    {
        $meminfo = $this->parseMeminfo();
        $total = $meminfo['MemTotal'] ?? 0;
        $free = $meminfo['MemFree'] ?? 0;
        $buffers = $meminfo['Buffers'] ?? 0;
        $cached = $meminfo['Cached'] ?? 0;
        $used = max(0, $total - $free - $buffers - $cached);
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        return [
            'total' => $this->formatBytes($total * 1024),
            'used' => $this->formatBytes($used * 1024),
            'free' => $this->formatBytes(($free + $buffers + $cached) * 1024),
            'percent' => $percent,
            'color' => $this->colorThreshold($percent, [50, 70, 80, 90]),
        ];
    }

    private function getSwapUsage(): array
    {
        $meminfo = $this->parseMeminfo();
        $total = $meminfo['SwapTotal'] ?? 0;
        $free = $meminfo['SwapFree'] ?? 0;
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        return [
            'total' => $this->formatBytes($total * 1024),
            'used' => $this->formatBytes($used * 1024),
            'free' => $this->formatBytes($free * 1024),
            'percent' => $percent,
            'color' => $this->colorThreshold($percent, [20, 50, 70, 85]),
        ];
    }

    private function getCpuLoad(): array
    {
        $load = sys_getloadavg();
        $cpuCount = (int) trim(ShellService::exec('nproc') ?: '1');
        $loadPercent = $cpuCount > 0 ? round(($load[0] ?? 0) / $cpuCount * 100, 1) : 0;
        return [
            '1min' => $load[0] ?? 0,
            '5min' => $load[1] ?? 0,
            '15min' => $load[2] ?? 0,
            'cores' => $cpuCount,
            'percent' => min(100, $loadPercent),
            'color' => $this->colorThreshold($loadPercent, [25, 50, 75, 90]),
        ];
    }

    private function parseMeminfo(): array
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

    private function colorThreshold(float $percent, array $thresholds): string
    {
        $colors = ['green', 'blue', 'yellow', 'orange', 'red'];
        foreach ($thresholds as $i => $threshold) {
            if ($percent <= $threshold) return $colors[$i] ?? 'red';
        }
        return 'red';
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
    }
}
