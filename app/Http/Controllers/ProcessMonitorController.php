<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class ProcessMonitorController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $processes = $this->listProcesses();
        $load = sys_getloadavg();
        $uptime = trim($this->process()->run('uptime -p')->output());

        return view('process-monitor.index', compact('processes', 'load', 'uptime'));
    }

    public function top()
    {
        $result = $this->process()->run("top -bn1 | head -30");
        return new JsonResponse(['output' => (string) $result->output()]);
    }

    public function kill(Request $request)
    {
        $request->validate([
            'pid' => 'required|integer',
            'signal' => 'nullable|in:TERM,KILL,HUP',
        ]);
        $signal = $request->signal ?? 'TERM';
        $result = $this->process()->run("kill -{$signal} {$request->pid} 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->successful() ? "Sent {$signal} to PID {$request->pid}." : $result->errorOutput());
    }

    public function netstat()
    {
        $result = $this->process()->run("ss -tulnp 2>/dev/null || netstat -tulnp 2>/dev/null");
        $connections = $result->output();
        return view('process-monitor.netstat', compact('connections'));
    }

    protected function listProcesses(): array
    {
        $result = $this->process()->run("ps aux --sort=-%mem | head -50");
        $lines = array_filter(explode("\n", trim($result->output())));
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
}
