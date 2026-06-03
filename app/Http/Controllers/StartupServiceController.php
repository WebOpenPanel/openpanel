<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class StartupServiceController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $services = [];
        $output = $this->process()->run("systemctl list-unit-files --type=service --no-pager --plain 2>/dev/null")->output();
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(\S+)\s+(enabled|disabled|masked|static)\s/', $line, $m)) {
                $name = $m[1];
                $enabled = $m[2];
                $active = trim($this->process()->run("systemctl is-active {$name} 2>/dev/null")->output());
                $services[] = ['name' => $name, 'enabled' => $enabled, 'active' => $active];
            }
        }
        return view('startup-services.index', compact('services'));
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'service' => 'required|string',
            'action' => 'required|in:enable,disable,start,stop,restart',
        ]);

        $svc = escapeshellarg($request->service);
        $act = escapeshellarg($request->action);
        $result = $this->process()->run("systemctl {$act} {$svc} 2>&1");

        return back()->with(
            $result->successful() ? 'success' : 'error',
            $result->successful() ? "Service {$request->action}ed." : $result->errorOutput()
        );
    }
}
