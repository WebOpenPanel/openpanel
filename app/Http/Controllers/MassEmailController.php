<?php

namespace App\Http\Controllers;

use App\Models\LinuxAuthUser;
use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class MassEmailController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $users = LinuxAuthUser::all();
        return view('mass-email.index', compact('users'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:10000',
            'recipients' => 'required|in:all,resellers,users',
        ]);

        $users = match ($request->recipients) {
            'resellers' => LinuxAuthUser::resellers(),
            'users' => LinuxAuthUser::clients(),
            default => LinuxAuthUser::all(),
        };

        $sent = 0;
        $hostname = trim((string) $this->process()->run("hostname -f 2>/dev/null")->output());

        foreach ($users as $user) {
            $email = $user->username . '@' . $hostname;
            $subject = escapeshellarg($request->subject);
            $body = escapeshellarg($request->message);
            $this->process()->run("echo {$body} | mail -s {$subject} {$email} 2>/dev/null");
            $sent++;
        }

        return back()->with('success', "Email sent to {$sent} users.");
    }
}
