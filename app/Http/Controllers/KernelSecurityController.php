<?php

namespace App\Http\Controllers;

use App\Services\KernelSecurityService;
use Illuminate\Http\Request;

class KernelSecurityController extends Controller
{
    public function index()
    {
        $score = KernelSecurityService::getSecurityScore();
        $modules = KernelSecurityService::getLoadedModules();
        $blacklisted = KernelSecurityService::getBlacklistedModules();
        $sysctl = KernelSecurityService::getSysctlValues();
        return view('kernel-security.index', compact('score', 'modules', 'blacklisted', 'sysctl'));
    }

    public function blacklist(Request $request)
    {
        $request->validate(['module' => 'required|string']);
        $result = KernelSecurityService::blacklistModule($request->input('module'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function unblacklist(Request $request)
    {
        $request->validate(['module' => 'required|string']);
        $result = KernelSecurityService::unblacklistModule($request->input('module'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function harden()
    {
        $result = KernelSecurityService::applySysctlHardening();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
