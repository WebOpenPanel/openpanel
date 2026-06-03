<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class PhpIniEditorController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $iniPath = trim($this->process()->run("php --ini | grep 'Loaded Configuration' | awk '{print $4}'")->output());
        $config = file_exists($iniPath) ? file_get_contents($iniPath) : '';
        $sections = $this->parseIni($config);

        return view('phpini.index', compact('config', 'iniPath', 'sections'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required|string',
        ]);

        $iniPath = trim($this->process()->run("php --ini | grep 'Loaded Configuration' | awk '{print $4}'")->output());
        $config = file_get_contents($iniPath);

        $key = preg_quote($request->key, '/');
        if (preg_match("/^;?{$key}\s*=/m", $config)) {
            $config = preg_replace("/^;?{$key}\s*=.*/m", "{$request->key} = {$request->value}", $config);
        } else {
            $config .= "\n{$request->key} = {$request->value}\n";
        }

        file_put_contents($iniPath, $config);
        $this->process()->run("systemctl restart php-fpm 2>/dev/null");

        return back()->with('success', "{$request->key} updated.");
    }

    public function saveFull(Request $request)
    {
        $request->validate(['config' => 'required|string']);
        $iniPath = trim($this->process()->run("php --ini | grep 'Loaded Configuration' | awk '{print $4}'")->output());
        file_put_contents($iniPath, $request->config);
        $this->process()->run("systemctl restart php-fpm 2>/dev/null");
        return back()->with('success', 'php.ini saved.');
    }

    protected function parseIni(string $config): array
    {
        $sections = [];
        $current = 'PHP';
        foreach (explode("\n", $config) as $line) {
            $line = trim($line);
            if (preg_match('/^\[(.+)\]$/', $line, $m)) {
                $current = $m[1];
            } elseif ($line && $line[0] !== ';' && strpos($line, '=') !== false) {
                [$key, $val] = explode('=', $line, 2);
                $sections[$current][trim($key)] = trim($val);
            }
        }
        return $sections;
    }
}
