<?php

namespace App\Http\Controllers;

use App\Services\PeclService;
use App\Services\PhpService;
use Illuminate\Http\Request;

class PeclController extends Controller
{
    public function index()
    {
        $phpVersions = PhpService::getInstalledVersions();
        $selectedPhp = request('php', '8.3');
        $installed = PeclService::getInstalledExtensions($selectedPhp);
        return view('pecl.index', compact('phpVersions', 'installed', 'selectedPhp'));
    }

    public function search(Request $request)
    {
        $request->validate(['query' => 'required|string|min:2']);
        $results = PeclService::search($request->input('query'));
        return view('pecl.search', compact('results'));
    }

    public function install(Request $request)
    {
        $request->validate(['extension' => 'required|string', 'php' => 'nullable|string']);
        $php = $request->input('php', '8.3');
        $result = PeclService::install($request->input('extension'), $php);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? $result['message'] ?? 'Installation completed.');
    }

    public function uninstall(Request $request)
    {
        $request->validate(['extension' => 'required|string', 'php' => 'nullable|string']);
        $php = $request->input('php', '8.3');
        $result = PeclService::uninstall($request->input('extension'), $php);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Done.');
    }

    public function toggle(Request $request)
    {
        $request->validate(['extension' => 'required|string', 'action' => 'required|in:enable,disable', 'php' => 'nullable|string']);
        $php = $request->input('php', '8.3');
        $action = $request->input('action');
        $result = $action === 'enable' ? PeclService::enable($request->input('extension'), $php) : PeclService::disable($request->input('extension'), $php);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
