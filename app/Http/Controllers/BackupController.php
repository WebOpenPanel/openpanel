<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\BackupConfig;
use App\Models\UserAccount;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class BackupController extends Controller
{
    public function index()
    {
        $backups = Backup::with('userAccount')->latest()->paginate(20);
        $config = BackupConfig::first();
        $serverBackups = BackupService::listBackups();
        $backupSize = BackupService::getBackupSize();
        return view('backups.index', compact('backups', 'config', 'serverBackups', 'backupSize'));
    }

    public function config()
    {
        $config = BackupConfig::first() ?? new BackupConfig();
        $accounts = UserAccount::orderBy('domain')->get();
        return view('backups.config', compact('config', 'accounts'));
    }

    public function saveConfig(Request $request)
    {
        $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
            'retention_days' => 'required|integer|min:1',
            'destination' => 'required|in:local,remote,s3,ftp',
            'remote_host' => 'nullable|string',
            'remote_user' => 'nullable|string',
            'remote_path' => 'nullable|string',
            'notification_email' => 'nullable|email',
        ]);
        $data = $request->all();
        $data['enabled'] = $request->boolean('enabled');
        $data['include_databases'] = $request->boolean('include_databases');
        $data['include_email'] = $request->boolean('include_email');
        $data['include_files'] = $request->boolean('include_files');
        BackupConfig::updateOrCreate([], $data);
        return back()->with('success', 'Backup configuration saved.');
    }

    public function restore(Backup $backup)
    {
        $result = BackupService::restoreBackup($backup);
        return back()->with('success', "Restore from '{$backup->filename}' initiated.");
    }

    public function destroy(Backup $backup)
    {
        BackupService::deleteBackup($backup);
        return back()->with('success', 'Backup deleted.');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,account,database,files',
            'user_account_id' => 'nullable|exists:user_accounts,id',
        ]);
        match ($request->type) {
            'full' => BackupService::generateFullBackup(),
            'account' => $request->user_account_id ? BackupService::generateAccountBackup(
                UserAccount::find($request->user_account_id)->username,
                $request->user_account_id
            ) : null,
            'database' => BackupService::generateDatabaseBackup($request->database ?? 'all'),
            'files' => BackupService::generateFilesBackup($request->path ?? '/home'),
        };
        return back()->with('success', 'Backup generated.');
    }

    public function download(Backup $backup)
    {
        $path = BackupService::downloadBackup($backup->path);
        if (!$path) return back()->with('error', 'File not found.');
        return Response::download($path);
    }

    public function cleanup()
    {
        $deleted = BackupService::cleanupOldBackups();
        return back()->with('success', "{$deleted} old backups cleaned up.");
    }

    // SQLite3-based backup manager
    public function managerIndex()
    {
        $backups = BackupService::managerListBackups();
        return view('backups.manager', compact('backups'));
    }

    public function managerSave(Request $request)
    {
        $data = $request->all();
        $result = BackupService::managerSaveBackup($data);
        if ($result['success']) {
            return back()->with('success', 'Backup configuration saved. ID: ' . $result['id']);
        }
        return back()->with('error', $result['message'] ?? 'Failed to save.');
    }

    public function managerDelete(int $id)
    {
        BackupService::managerDeleteBackup($id);
        return back()->with('success', 'Backup configuration deleted.');
    }

    public function managerToggle(int $id, Request $request)
    {
        $status = $request->input('status', 0);
        BackupService::managerUpdateStatus($id, (int) $status);
        return back()->with('success', 'Backup status updated.');
    }

    public function managerRun(int $id)
    {
        BackupService::managerRunBackup($id);
        return back()->with('success', 'Backup job started.');
    }

    public function managerSetDefault(int $id)
    {
        BackupService::managerSetDefault($id);
        return back()->with('success', 'Default backup set.');
    }

    public function managerMonitor()
    {
        $log = BackupService::managerMonitorLog(20);
        $restoreLog = BackupService::managerMonitorRestoreLog(20);
        return view('backups.monitor', compact('log', 'restoreLog'));
    }

    public function managerEdit(int $id)
    {
        $backup = BackupService::managerGetBackup($id);
        $accounts = UserAccount::orderBy('domain')->get();
        return view('backups.manager_edit', compact('backup', 'accounts'));
    }
}
