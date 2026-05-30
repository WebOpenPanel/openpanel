<?php

namespace App\Http\Controllers;

use App\Services\MigrationService;
use Illuminate\Http\Request;

class MigrationController extends Controller
{
    public function index()
    {
        $log = MigrationService::getMigrationLog(20);
        $backups = MigrationService::listBackups();
        return view('migration.index', compact('log', 'backups'));
    }

    public function serverTransfer(Request $request)
    {
        $request->validate([
            'remote_host' => 'required|string',
            'remote_port' => 'nullable|integer',
            'remote_user' => 'nullable|string',
            'remote_key' => 'nullable|string',
            'username' => 'required|string',
        ]);
        $result = MigrationService::serverTransfer($request->all());
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function cpanelTransfer(Request $request)
    {
        $request->validate([
            'remote_host' => 'required|string',
            'remote_port' => 'nullable|integer',
            'remote_user' => 'nullable|string',
            'remote_key' => 'nullable|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);
        $result = MigrationService::cpanelTransfer($request->all());
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function log()
    {
        $log = MigrationService::getMigrationLog(50);
        return view('migration.log', compact('log'));
    }

    public function restore(Request $request)
    {
        $request->validate(['backup_file' => 'required|string', 'username' => 'required|string']);
        $output = MigrationService::restoreFromBackup($request->backup_file, $request->username);
        return back()->with('output', $output)->with('success', 'Restore initiated.');
    }
}
