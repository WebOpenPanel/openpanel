<?php

namespace App\Http\Controllers;

use App\Services\AntiSpamService;

class AntiSpamController extends Controller
{
    public function index()
    {
        $installed = AntiSpamService::isSpamhausInstalled();
        return view('antispam.index', compact('installed'));
    }

    public function install()
    {
        $result = AntiSpamService::installSpamhaus();
        return back()->with($result ? 'success' : 'error', $result ? 'Spamhaus installed.' : 'Failed to install.');
    }

    public function uninstall()
    {
        $result = AntiSpamService::uninstallSpamhaus();
        return back()->with($result ? 'success' : 'error', $result ? 'Spamhaus removed.' : 'Failed to remove.');
    }

    public function listBlocked()
    {
        $output = AntiSpamService::listBlockedIps();
        return back()->with('output', $output);
    }
}
