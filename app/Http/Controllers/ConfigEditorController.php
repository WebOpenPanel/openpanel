<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Process\Factory as ProcessFactory;

class ConfigEditorController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    protected $allowedFiles = [
        '/etc/named.conf',
        '/etc/ssh/sshd_config',
        '/etc/sysconfig/selinux',
        '/etc/hosts.allow',
        '/etc/hosts.deny',
        '/etc/security/limits.conf',
        '/etc/my.cnf',
        '/etc/php.ini',
        '/etc/postfix/main.cf',
        '/etc/postfix/master.cf',
        '/etc/dovecot/dovecot.conf',
        '/etc/nginx/nginx.conf',
        '/etc/php-fpm.conf',
        '/etc/fstab',
        '/etc/resolv.conf',
        '/etc/hosts',
    ];

    public function index()
    {
        return view('config-editor.index', [
            'allowedFiles' => $this->allowedFiles,
        ]);
    }

    public function load(Request $request)
    {
        $request->validate(['file' => 'required|string']);

        $file = $request->input('file');

        if (!$this->isAllowed($file)) {
            return back()->with('error', 'Access denied to this file.');
        }

        if (!file_exists($file)) {
            return back()->with('error', 'File not found: ' . $file);
        }

        $content = file_get_contents($file);
        return view('config-editor.edit', compact('file', 'content'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'file' => 'required|string',
            'content' => 'required|string',
        ]);

        $file = $request->input('file');

        if (!$this->isAllowed($file)) {
            return back()->with('error', 'Access denied to this file.');
        }

        $backupPath = $file . '.bak.' . date('YmdHis');
        if (file_exists($file)) {
            copy($file, $backupPath);
        }

        if (file_put_contents($file, $request->content) === false) {
            return back()->with('error', 'Failed to write file.');
        }

        return back()->with('success', 'File saved. Backup at: ' . basename($backupPath));
    }

    public function syntaxCheck(Request $request)
    {
        $request->validate(['file' => 'required|string']);
        $file = $request->input('file');

        if (!$this->isAllowed($file)) {
            return new JsonResponse(['valid' => false, 'message' => 'Access denied.']);
        }

        if (!file_exists($file)) {
            return new JsonResponse(['valid' => false, 'message' => 'File not found.']);
        }

        $escapedFile = escapeshellarg($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $checks = [
            'conf' => "nginx -t -c {$escapedFile} 2>&1",
            'cnf' => "mysqld --help --verbose 2>&1 | head -5",
        ];

        $cmd = $checks[$ext] ?? "cat {$escapedFile} > /dev/null 2>&1 && echo 'File is readable'";
        $output = (string) $this->process()->run($cmd)->output();

        return new JsonResponse([
            'valid' => !str_contains($output, 'error') && !str_contains($output, 'Error'),
            'message' => $output,
        ]);
    }

    protected function isAllowed(string $file): bool
    {
        if (in_array($file, $this->allowedFiles)) {
            return true;
        }

        $allowedDirs = ['/etc/nginx/', '/etc/php-fpm.d/', '/etc/postfix/', '/etc/dovecot/'];
        foreach ($allowedDirs as $dir) {
            if (str_starts_with($file, $dir)) {
                return true;
            }
        }

        return false;
    }
}
