<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class RestoreBackupController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $backupDir = config('openpanel.paths.backup_dir', '/backup');
        $files = [];

        if (is_dir($backupDir)) {
            foreach (glob("{$backupDir}/*.tar.gz") as $file) {
                $files[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }

        usort($files, fn($a, $b) => strtotime($b['modified']) - strtotime($a['modified']));
        return view('restore-backup.index', compact('files'));
    }

    public function restore(Request $request)
    {
        $request->validate([
            'file' => 'required|string',
            'username' => 'required|string|regex:/^[a-z0-9_]+$/',
        ]);

        $file = $request->input('file');
        $username = $request->input('username');
        $backupDir = config('openpanel.paths.backup_dir', '/backup');
        $path = $backupDir . '/' . basename($file);

        if (!file_exists($path)) {
            return back()->with('error', 'Backup file not found.');
        }

        if (!str_ends_with($path, '.tar.gz') && !str_ends_with($path, '.tgz')) {
            return back()->with('error', 'Invalid file type.');
        }

        $home = '/home/' . $username;

        if (!is_dir($home)) {
            return back()->with('error', "User home {$home} does not exist. Create user first.");
        }

        $homeEsc = escapeshellarg($home);
        $pathEsc = escapeshellarg($path);
        $userEsc = escapeshellarg($username);

        $result = $this->process()->run("tar -xzf {$pathEsc} -C {$homeEsc} --strip-components=1 --overwrite 2>&1");
        $this->process()->run("chown -R {$userEsc}:{$userEsc} {$homeEsc} 2>&1");

        return back()->with(
            $result->successful() ? 'success' : 'error',
            $result->successful() ? "Backup restored for {$username}" : $result->errorOutput()
        );
    }

    public function upload(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|max:5242880',
        ]);

        $backupDir = config('openpanel.paths.backup_dir', '/backup');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $request->file('backup_file')->move($backupDir, $request->file('backup_file')->getClientOriginalName());
        return back()->with('success', 'Backup uploaded.');
    }
}
