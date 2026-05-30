<?php

namespace App\Http\Controllers;

use App\Services\ClamavService;
use Illuminate\Http\Request;

class ClamavController extends Controller
{
    public function index()
    {
        $installed = ClamavService::isInstalled();
        $version = $installed ? ClamavService::getVersion() : '';
        $quarantine = $installed ? ClamavService::getQuarantine() : [];
        $logs = $installed ? ClamavService::getScanLogs(10) : [];
        return view('clamav.index', compact('installed', 'version', 'quarantine', 'logs'));
    }

    public function install()
    {
        $result = ClamavService::install();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function updateDefinitions()
    {
        $result = ClamavService::updateDefinitions();
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Done.');
    }

    public function scanUser(Request $request)
    {
        $request->validate(['user' => 'required|string']);
        $result = ClamavService::scanUser($request->input('user'));
        return back()->with($result['infected'] > 0 ? 'warning' : 'success', "Scan complete. Infected files: {$result['infected']}");
    }

    public function scanAll()
    {
        $results = ClamavService::scanAllUsers();
        $totalInfected = array_sum(array_column($results, 'infected'));
        return back()->with($totalInfected > 0 ? 'warning' : 'success', "All users scanned. Total infected: {$totalInfected}");
    }

    public function scanPath(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $result = ClamavService::scanPath($request->input('path'));
        return back()->with($result['infected'] > 0 ? 'warning' : 'success', "Scan complete. Infected: {$result['infected']}");
    }

    public function restore(Request $request)
    {
        $request->validate(['path' => 'required|string', 'restore_to' => 'required|string']);
        $result = ClamavService::restoreFromQuarantine($request->input('path'), $request->input('restore_to'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function deleteQuarantine(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $result = ClamavService::deleteFromQuarantine($request->input('path'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function viewLog(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $content = ClamavService::readScanLog($request->input('path'));
        return view('clamav.log', compact('content'));
    }
}
