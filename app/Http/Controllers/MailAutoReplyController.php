<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class MailAutoReplyController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $replies = $this->listAutoReplies();
        return view('mail-autoreply.index', compact('replies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:10000',
        ]);

        $email = strtolower($request->email);
        $user = explode('@', $email)[0];
        $domain = explode('@', $email)[1];

        $home = $this->getHomeDir($user);
        if (!$home) {
            return back()->with('error', "User '{$user}' not found.");
        }

        $vacationDir = "{$home}/.vacation";
        mkdir($vacationDir, 0755, true);

        $msgFile = "{$vacationDir}/{$user}@{$domain}.msg";
        $msgContent = "Subject: {$request->subject}\n\n{$request->body}";
        file_put_contents($msgFile, $msgContent);

        $aliasLine = "{$user}@{$domain}: \"|/usr/bin/vacation {$user}\"";
        $aliasFile = "{$home}/.forward";
        file_put_contents($aliasFile, $aliasLine);
        chown($aliasFile, $user);

        return back()->with('success', "Auto-reply set for {$email}.");
    }

    public function destroy(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower($request->email);
        $user = explode('@', $email)[0];

        $home = $this->getHomeDir($user);
        if ($home) {
            @unlink("{$home}/.forward");
            @unlink("{$home}/.vacation/{$email}.msg");
        }

        return back()->with('success', "Auto-reply removed for {$email}.");
    }

    protected function listAutoReplies(): array
    {
        $replies = [];
        $result = $this->process()->run("find /home -name '.forward' -exec grep -l vacation {} \\; 2>/dev/null");
        foreach (array_filter(explode("\n", trim($result->output()))) as $file) {
            $user = explode('/', $file)[2] ?? 'unknown';
            $content = file_get_contents($file);
            if (preg_match('/vacation\s+(\w+)/', $content, $m)) {
                $replies[] = ['user' => $m[1], 'file' => $file];
            }
        }
        return $replies;
    }

    protected function getHomeDir(string $username): ?string
    {
        $result = $this->process()->run("getent passwd {$username} | cut -d: -f6");
        $home = trim($result->output());
        return $home ?: null;
    }
}
