<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class MailExplorerController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $domains = [];
        $vhostFile = config('openpanel.paths.postfix_vhost', '/etc/postfix/vhost');
        if (file_exists($vhostFile)) {
            $domains = array_filter(explode("\n", trim(file_get_contents($vhostFile))));
        }
        return view('mail-explorer.index', compact('domains'));
    }

    public function browse(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'user' => 'nullable|string',
        ]);

        $domain = $request->domain;
        $user = $request->user;
        $basePath = $user ? "/home/{$user}/mail/{$domain}" : "/var/mail";

        if (!is_dir($basePath)) {
            $basePath = "/home/{$domain}/mail/{$domain}";
        }

        $files = [];
        if (is_dir($basePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        return view('mail-explorer.browse', compact('domain', 'user', 'files', 'basePath'));
    }

    public function viewFile(Request $request)
    {
        $request->validate(['path' => 'required|string']);

        if (!str_starts_with($request->path, '/home/') && !str_starts_with($request->path, '/var/mail')) {
            return back()->with('error', 'Access denied.');
        }

        if (!file_exists($request->path)) {
            return back()->with('error', 'File not found.');
        }

        $content = file_get_contents($request->path, false, null, 0, 512000);
        return view('mail-explorer.file', ['path' => $request->path, 'content' => $content]);
    }
}
