<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class LogrotateController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $configs = [];
        $dir = '/etc/logrotate.d';
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if ($f !== '.' && $f !== '..') {
                    $configs[] = $f;
                }
            }
        }
        return view('logrotate.index', compact('configs'));
    }

    public function edit(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $path = '/etc/logrotate.d/' . basename($request->name);
        if (!file_exists($path)) {
            return back()->with('error', 'Config not found.');
        }
        $content = file_get_contents($path);
        return view('logrotate.edit', ['name' => $request->name, 'content' => $content]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'content' => 'required|string',
        ]);

        $path = '/etc/logrotate.d/' . basename($request->name);
        file_put_contents($path, $request->content);
        return back()->with('success', 'Logrotate config saved.');
    }

    public function test(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $path = '/etc/logrotate.d/' . basename($request->name);
        $output = $this->process()->run("logrotate -d {$path} 2>&1")->output();
        return back()->with('info', $output);
    }
}
