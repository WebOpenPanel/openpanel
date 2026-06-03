<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Process\Factory as ProcessFactory;

class LiveMonitorController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        return view('live-monitor.index');
    }

    public function data()
    {
        $cpu = sys_getloadavg();
        $meminfo = $this->getMeminfo();
        $disk = disk_free_space('/');
        $diskTotal = disk_total_space('/');

        $net = $this->process()->run("cat /proc/net/dev | grep -E 'eth|ens|enp' | head -1");
        $netParts = preg_split('/\s+/', trim($net->output()));

        return new JsonResponse([
            'cpu' => $cpu[0],
            'memory' => [
                'total' => $meminfo['MemTotal'] ?? 0,
                'used' => ($meminfo['MemTotal'] ?? 0) - ($meminfo['MemAvailable'] ?? 0),
                'percent' => ($meminfo['MemTotal'] ?? 0) > 0
                    ? round(((($meminfo['MemTotal'] ?? 0) - ($meminfo['MemAvailable'] ?? 0)) / ($meminfo['MemTotal'] ?? 1)) * 100, 1)
                    : 0,
            ],
            'disk' => [
                'total' => $diskTotal,
                'free' => $disk,
                'percent' => $diskTotal > 0 ? round((($diskTotal - $disk) / $diskTotal) * 100, 1) : 0,
            ],
            'network' => [
                'rx' => (int) ($netParts[1] ?? 0),
                'tx' => (int) ($netParts[9] ?? 0),
            ],
            'uptime' => trim((string) $this->process()->run('uptime -p')->output()),
            'connections' => (int) trim((string) $this->process()->run("ss -t state established | wc -l")->output()),
        ]);
    }

    protected function getMeminfo(): array
    {
        $info = [];
        if (file_exists('/proc/meminfo')) {
            foreach (explode("\n", file_get_contents('/proc/meminfo')) as $line) {
                if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                    $info[$m[1]] = (int) $m[2];
                }
            }
        }
        return $info;
    }
}
