<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class ScreenController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $installed = $this->process()->run("which screen 2>/dev/null")->successful();
        $sessions = [];

        if ($installed) {
            $output = $this->process()->run("screen -ls 2>/dev/null")->output();
            if (preg_match_all('/(\d+\.\S+)/', $output, $m)) {
                $sessions = $m[1];
            }
        }

        return view('screen.index', compact('installed', 'sessions'));
    }

    public function install()
    {
        $result = $this->process()->run("dnf -y install screen 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', 'Screen installed.');
    }

    public function create(Request $request)
    {
        $request->validate(['name' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/']);
        $name = escapeshellarg($request->name);
        $this->process()->run("screen -dmS {$name} 2>&1");
        return back()->with('success', "Screen session '{$request->name}' created.");
    }

    public function kill(Request $request)
    {
        $request->validate(['session' => 'required|string']);
        $session = escapeshellarg($request->input('session'));
        $this->process()->run("screen -S {$session} -X quit 2>&1");
        return back()->with('success', "Screen session '{$request->input('session')}' killed.");
    }
}
