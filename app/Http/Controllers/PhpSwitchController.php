<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class PhpSwitchController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $current = trim((string) $this->process()->run("php -v 2>/dev/null | head -1 | awk '{print \$2}'")->output());
        $installed = [];
        $output = $this->process()->run("alternatives --display php 2>/dev/null | grep php-")->output();

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/php-([\d.]+)/', $line, $m)) {
                $installed[] = $m[1];
            }
        }

        if (empty($installed) && $current) {
            $installed[] = $current;
        }

        return view('php-switch.index', compact('current', 'installed'));
    }

    public function switchVersion(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $version = preg_replace('/[^0-9.]/', '', $request->version);
        $result = $this->process()->run("alternatives --set php /usr/bin/php-{$version} 2>&1");

        if ($result->successful()) {
            $this->process()->run("systemctl restart php-fpm 2>/dev/null");
            return back()->with('success', "PHP switched to {$version}");
        }

        return back()->with('error', 'Switch failed: ' . $result->errorOutput());
    }
}
