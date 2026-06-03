<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class NetdataController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $installed = $this->process()->run("which netdata 2>/dev/null")->successful();
        $running = $installed && trim((string) $this->process()->run("systemctl is-active netdata 2>/dev/null")->output()) === 'active';
        $port = '19999';
        return view('netdata.index', compact('installed', 'running', 'port'));
    }

    public function install()
    {
        $result = $this->process()->run("bash <(curl -Ss https://my-netdata.io/kickstart.sh) --dont-wait 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->output());
    }

    public function toggle(Request $request)
    {
        $request->validate(['action' => 'required|in:start,stop,restart']);
        $result = $this->process()->run("systemctl {$request->action} netdata 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', "Netdata {$request->action}ed.");
    }
}
