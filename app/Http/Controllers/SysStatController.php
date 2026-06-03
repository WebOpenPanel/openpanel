<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class SysStatController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $installed = $this->process()->run("which sar 2>/dev/null")->successful();
        $stats = $installed ? $this->process()->run("sar -u -r -n DEV 2>/dev/null | tail -30")->output() : '';
        return view('sysstat.index', compact('installed', 'stats'));
    }

    public function install()
    {
        $result = $this->process()->run("dnf -y install sysstat 2>&1");
        $this->process()->run("systemctl enable --now sysstat 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->output());
    }

    public function report(Request $request)
    {
        $type = $request->get('type', 'cpu');
        $flag = match ($type) {
            'cpu' => '-u',
            'memory' => '-r',
            'disk' => '-d',
            'network' => '-n DEV',
            default => '-u',
        };

        $output = $this->process()->run("sar {$flag} 2>/dev/null | tail -50")->output();
        return view('sysstat.report', compact('output', 'type'));
    }
}
