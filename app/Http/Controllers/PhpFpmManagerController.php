<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class PhpFpmManagerController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $version = $this->getVersion();
        $pools = $this->listPools();
        $config = $this->getMainConfig();
        $status = $this->getStatus();

        return view('php-fpm.index', compact('version', 'pools', 'config', 'status'));
    }

    public function editConfig()
    {
        $config = file_get_contents('/etc/php-fpm.d/www.conf') ?: '';
        return view('php-fpm.config', compact('config'));
    }

    public function saveConfig(Request $request)
    {
        $request->validate(['config' => 'required|string']);
        file_put_contents('/etc/php-fpm.d/www.conf', $request->config);
        $result = $this->process()->run("systemctl restart php-fpm 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->successful() ? 'Config saved.' : $result->errorOutput());
    }

    public function editPool(Request $request)
    {
        $request->validate(['pool' => 'required|string']);
        $pool = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->pool);
        $config = file_get_contents("/etc/php-fpm.d/{$pool}.conf") ?: 'Pool config not found.';
        return view('php-fpm.pool', compact('config', 'pool'));
    }

    public function savePool(Request $request)
    {
        $request->validate([
            'pool' => 'required|string',
            'config' => 'required|string',
        ]);
        $pool = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->pool);
        file_put_contents("/etc/php-fpm.d/{$pool}.conf", $request->config);
        $result = $this->process()->run("systemctl restart php-fpm 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->successful() ? "Pool '{$pool}' saved." : $result->errorOutput());
    }

    public function service(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:start,stop,restart,status'])['action'];
        $result = $this->process()->run("systemctl {$action} php-fpm 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', trim($result->output() . $result->errorOutput()));
    }

    protected function getVersion(): string
    {
        $result = $this->process()->run("php-fpm -v 2>/dev/null | head -1 | awk '{print $2}'");
        return trim($result->output()) ?: 'unknown';
    }

    protected function listPools(): array
    {
        $pools = [];
        $dir = '/etc/php-fpm.d';
        if (is_dir($dir)) {
            foreach (glob("{$dir}/*.conf") as $file) {
                $pools[] = basename($file, '.conf');
            }
        }
        return $pools;
    }

    protected function getMainConfig(): string
    {
        return file_get_contents('/etc/php-fpm.conf') ?: '';
    }

    protected function getStatus(): string
    {
        $result = $this->process()->run("systemctl is-active php-fpm 2>/dev/null");
        return trim($result->output());
    }
}
