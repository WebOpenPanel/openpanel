<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class DiskQuotaController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $quotas = $this->listQuotas();
        $enabled = $this->isQuotaEnabled();

        return view('disk-quota.index', compact('quotas', 'enabled'));
    }

    public function setUserQuota(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'soft_mb' => 'required|integer|min:0',
            'hard_mb' => 'required|integer|min:0',
        ]);

        $user = $request->username;
        $soft = $request->soft_mb . 'M';
        $hard = $request->hard_mb . 'M';

        $result = $this->process()->run("setquota -u {$user} {$soft} {$hard} 0 0 / 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Set quota failed: ' . $result->errorOutput());
        }
        return back()->with('success', "Quota set for '{$user}'.");
    }

    public function removeUserQuota(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        $result = $this->process()->run("setquota -u {$request->username} 0 0 0 0 / 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->successful() ? "Quota removed for '{$request->username}'." : $result->errorOutput());
    }

    public function report()
    {
        $result = $this->process()->run("repquota -a 2>/dev/null");
        $report = $result->output();
        return view('disk-quota.report', compact('report'));
    }

    protected function isQuotaEnabled(): bool
    {
        $result = $this->process()->run("mount | grep ' / ' | grep -c quota");
        return (int) trim($result->output()) > 0;
    }

    protected function listQuotas(): array
    {
        $result = $this->process()->run("repquota -a -s 2>/dev/null | grep -A1000 '---' | tail -n+2");
        $quotas = [];
        foreach (array_filter(explode("\n", trim($result->output()))) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 5 && $parts[0] !== '--') {
                $quotas[] = [
                    'user' => trim($parts[0], '+*'),
                    'used' => $parts[2],
                    'soft' => $parts[3],
                    'hard' => $parts[4],
                ];
            }
        }
        return $quotas;
    }
}
