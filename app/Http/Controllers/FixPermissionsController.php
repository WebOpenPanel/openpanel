<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class FixPermissionsController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $output = $this->process()->run("awk -F: '\$3 >= 1000 && \$3 < 65534 {print \$1}' /etc/passwd 2>/dev/null")->output();
        $users = array_filter(explode("\n", $output));
        return view('fix-permissions.index', compact('users'));
    }

    public function fix(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        $user = escapeshellarg($request->username);
        $home = "/home/{$request->username}";

        if (!is_dir($home)) {
            return back()->with('error', 'User home not found.');
        }

        $commands = [
            "chown -R {$user}:{$user} {$home}/public_html 2>&1",
            "find {$home}/public_html -type d -exec chmod 755 {} \; 2>&1",
            "find {$home}/public_html -type f -exec chmod 644 {} \; 2>&1",
            "chmod 750 {$home} 2>&1",
            "chmod 750 {$home}/public_html 2>&1",
            "chown {$user}:{$user} {$home}/.ssh 2>/dev/null",
            "chmod 700 {$home}/.ssh 2>/dev/null",
        ];

        $errors = [];
        foreach ($commands as $cmd) {
            $result = $this->process()->run($cmd);
            if ($result->errorOutput()) {
                $errors[] = $result->errorOutput();
            }
        }

        return back()->with(
            empty($errors) ? 'success' : 'warning',
            empty($errors) ? "Permissions fixed for {$request->username}" : implode("\n", $errors)
        );
    }

    public function fixAll()
    {
        $output = $this->process()->run("awk -F: '\$3 >= 1000 && \$3 < 65534 {print \$1}' /etc/passwd")->output();
        $users = array_filter(explode("\n", $output));
        $fixed = 0;

        foreach ($users as $user) {
            $home = "/home/{$user}";
            if (!is_dir($home)) continue;
            $this->process()->run("chown -R {$user}:{$user} {$home}/public_html 2>/dev/null");
            $this->process()->run("find {$home}/public_html -type d -exec chmod 755 {} \; 2>/dev/null");
            $this->process()->run("find {$home}/public_html -type f -exec chmod 644 {} \; 2>/dev/null");
            $fixed++;
        }

        return back()->with('success', "Permissions fixed for {$fixed} users.");
    }
}
