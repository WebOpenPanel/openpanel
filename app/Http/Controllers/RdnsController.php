<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Process\Factory as ProcessFactory;

class RdnsController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $serverIp = trim((string) $this->process()->run("hostname -I | awk '{print \$1}'")->output());
        return view('rdns.index', compact('serverIp'));
    }

    public function check(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ip = escapeshellarg($request->ip);
        $ptr = trim((string) $this->process()->run("dig +short -x {$ip} 2>/dev/null")->output());
        $hostname = trim((string) $this->process()->run("hostname -f 2>/dev/null")->output());

        return new JsonResponse([
            'ip' => $request->ip,
            'ptr_record' => $ptr ?: 'None',
            'server_hostname' => $hostname,
            'match' => str_contains($ptr, $hostname),
        ]);
    }
}
