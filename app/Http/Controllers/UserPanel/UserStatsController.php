<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;

class UserStatsController extends Controller
{
    protected function username(): string
    {
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    public function index()
    {
        $username = $this->username();
        $homePath = "/home/{$username}";

        $diskUsed = trim(ShellService::exec("du -sh {$homePath} 2>/dev/null | cut -f1") ?: '0');
        $bandwidth = ShellService::exec("vnstat --json 2>/dev/null");

        $bandwidthData = [];
        if ($bandwidth) {
            $data = json_decode($bandwidth, true);
            if ($data) {
                $bandwidthData = $data;
            }
        }

        $diskBreakdown = [];
        $dirs = ['public_html', 'mail', 'logs', 'tmp', '.trash'];
        foreach ($dirs as $dir) {
            $dirPath = $homePath . '/' . $dir;
            if (is_dir($dirPath)) {
                $size = trim(ShellService::exec("du -sh {$dirPath} 2>/dev/null | cut -f1") ?: '0');
                $diskBreakdown[$dir] = $size;
            }
        }

        $processCount = trim(ShellService::exec("ps -u {$username} --no-headers 2>/dev/null | wc -l") ?: '0');

        $lastLogin = ShellService::exec("last -n 1 {$username} 2>/dev/null | head -1");

        return view('user-panel.stats.index', compact(
            'username', 'diskUsed', 'bandwidthData',
            'diskBreakdown', 'processCount', 'lastLogin'
        ));
    }
}
