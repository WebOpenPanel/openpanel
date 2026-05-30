<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;

class UserFileController extends Controller
{
    protected function username(): string
    {
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    protected function homePath(): string
    {
        return '/home/' . $this->username();
    }

    public function index(Request $request)
    {
        $path = $request->get('path', '');
        $homePath = $this->homePath();
        $fullPath = realpath($homePath . '/' . $path);

        if (!$fullPath || !str_starts_with($fullPath, $homePath)) {
            $fullPath = $homePath;
            $path = '';
        }

        if (!is_dir($fullPath)) {
            return back()->with('error', 'Directory not found.');
        }

        $items = [];
        $entries = scandir($fullPath);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $entryPath = $fullPath . '/' . $entry;
            $items[] = [
                'name' => $entry,
                'path' => ltrim($path . '/' . $entry, '/'),
                'is_dir' => is_dir($entryPath),
                'size' => is_file($entryPath) ? filesize($entryPath) : 0,
                'modified' => filemtime($entryPath),
                'perms' => substr(sprintf('%o', fileperms($entryPath)), -4),
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
            return strcasecmp($a['name'], $b['name']);
        });

        $diskUsed = trim(ShellService::exec("du -sh {$homePath} 2>/dev/null | cut -f1") ?: '0');

        return view('user-panel.files.index', compact('items', 'path', 'diskUsed', 'homePath'));
    }

    public function readFile(Request $request)
    {
        $path = $request->get('path', '');
        $homePath = $this->homePath();
        $fullPath = realpath($homePath . '/' . $path);

        if (!$fullPath || !str_starts_with($fullPath, $homePath) || !is_file($fullPath)) {
            return back()->with('error', 'File not found.');
        }

        $content = file_get_contents($fullPath);
        $size = filesize($fullPath);

        if ($size > 5 * 1024 * 1024) {
            return back()->with('error', 'File too large to edit (max 5MB).');
        }

        return view('user-panel.files.edit', compact('content', 'path', 'fullPath'));
    }

    public function saveFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        $homePath = $this->homePath();
        $fullPath = realpath($homePath . '/' . $request->path);

        if (!$fullPath || !str_starts_with($fullPath, $homePath) || !is_file($fullPath)) {
            return back()->with('error', 'File not found.');
        }

        file_put_contents($fullPath, $request->content);

        return back()->with('success', 'File saved.');
    }

    public function createDirectory(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'name' => 'required|string|regex:/^[a-zA-Z0-9_\-\.]+$/',
        ]);

        $homePath = $this->homePath();
        $dir = $homePath . '/' . ltrim($request->path . '/' . $request->name, '/');

        if (!str_starts_with(realpath(dirname($dir)) ?: $dir, $homePath)) {
            return back()->with('error', 'Invalid path.');
        }

        mkdir($dir, 0755, true);
        ShellService::exec("chown -R " . $this->username() . ":" . $this->username() . " " . escapeshellarg($dir));

        return back()->with('success', 'Directory created.');
    }

    public function delete(Request $request)
    {
        $request->validate(['path' => 'required|string']);

        $homePath = $this->homePath();
        $fullPath = realpath($homePath . '/' . $request->path);

        if (!$fullPath || !str_starts_with($fullPath, $homePath)) {
            return back()->with('error', 'Invalid path.');
        }

        if (is_dir($fullPath)) {
            ShellService::exec("rm -rf " . escapeshellarg($fullPath));
        } else {
            unlink($fullPath);
        }

        return back()->with('success', 'Deleted.');
    }

    public function changePermissions(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'permissions' => 'required|string|regex:/^[0-7]{3,4}$/',
        ]);

        $homePath = $this->homePath();
        $fullPath = realpath($homePath . '/' . $request->path);

        if (!$fullPath || !str_starts_with($fullPath, $homePath)) {
            return back()->with('error', 'Invalid path.');
        }

        chmod($fullPath, octdec($request->permissions));

        return back()->with('success', 'Permissions changed.');
    }
}
