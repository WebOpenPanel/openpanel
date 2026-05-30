<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class UserBackupController extends Controller
{
    protected function username(): string
    {
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    public function index()
    {
        $username = $this->username();
        $backupDir = "/home/{$username}/backups";
        $backups = [];

        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*.tar.gz');
            foreach ($files as $file) {
                $backups[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'date' => filemtime($file),
                ];
            }
            usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
        }

        return view('user-panel.backups.index', compact('backups'));
    }

    public function create()
    {
        $username = $this->username();
        $backupDir = "/home/{$username}/backups";

        ShellService::exec("mkdir -p " . escapeshellarg($backupDir));

        $filename = $backupDir . '/' . $username . '_backup_' . date('Y-m-d_H-i-s') . '.tar.gz';
        $homePath = "/home/{$username}";

        ShellService::exec("tar -czf " . escapeshellarg($filename) . " -C " . escapeshellarg($homePath) . " --exclude=backups --exclude=.trash . 2>&1");

        return back()->with('success', 'Backup created: ' . basename($filename));
    }

    public function download(Request $request)
    {
        $request->validate(['file' => 'required|string']);

        $username = $this->username();
        $file = "/home/{$username}/backups/" . basename($request->file);

        if (!file_exists($file) || !str_starts_with($file, "/home/{$username}/backups/")) {
            return back()->with('error', 'Backup file not found.');
        }

        return Response::download($file);
    }

    public function delete(Request $request)
    {
        $request->validate(['file' => 'required|string']);

        $username = $this->username();
        $file = "/home/{$username}/backups/" . basename($request->file);

        if (!file_exists($file) || !str_starts_with($file, "/home/{$username}/backups/")) {
            return back()->with('error', 'Backup file not found.');
        }

        unlink($file);

        return back()->with('success', 'Backup deleted.');
    }

    public function restore(Request $request)
    {
        $request->validate(['file' => 'required|string']);

        $username = $this->username();
        $file = "/home/{$username}/backups/" . basename($request->file);
        $homePath = "/home/{$username}";

        if (!file_exists($file) || !str_starts_with($file, "/home/{$username}/backups/")) {
            return back()->with('error', 'Backup file not found.');
        }

        ShellService::exec("tar -xzf " . escapeshellarg($file) . " -C " . escapeshellarg($homePath) . " 2>&1");

        return back()->with('success', 'Backup restored.');
    }
}
