<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserEmailController extends Controller
{
    protected function username(): string
    {
        return auth()->user()->username;
    }

    public function index()
    {
        $accounts = DB::connection('openpanel')->table('email')
            ->where('user', $this->username())
            ->get();

        $domains = DB::connection('openpanel')->table('domains')
            ->where('user', $this->username())
            ->pluck('domain');

        return view('user-panel.email.index', compact('accounts', 'domains'));
    }

    public function forwarders()
    {
        $forwarders = DB::connection('openpanel')->table('email_forwarders')
            ->where('user', $this->username())
            ->get();

        $domains = DB::connection('openpanel')->table('domains')
            ->where('user', $this->username())
            ->pluck('domain');

        return view('user-panel.email.forwarders', compact('forwarders', 'domains'));
    }

    public function autoresponders()
    {
        $autoresponders = DB::connection('openpanel')->table('email_autoresponders')
            ->where('user', $this->username())
            ->get();

        $domains = DB::connection('openpanel')->table('domains')
            ->where('user', $this->username())
            ->pluck('domain');

        return view('user-panel.email.autoresponders', compact('autoresponders', 'domains'));
    }

    public function createAccount(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string|min:6',
            'quota' => 'integer|min:0',
        ]);

        $username = $this->username();
        $email = $request->email;
        $password = $request->password;
        $quota = $request->quota ?? 250;

        $parts = explode('@', $email);
        $mailUser = $parts[0];
        $domain = $parts[1] ?? '';

        $domainCheck = DB::connection('openpanel')->table('domains')
            ->where('user', $username)
            ->where('domain', $domain)
            ->exists();

        if (!$domainCheck) {
            return back()->with('error', 'Domain not found or not owned by you.');
        }

        ShellService::exec("echo '" . escapeshellarg($password) . "' | /usr/local/bin/mailadd {$username} {$email} {$quota} 2>&1");

        return back()->with('success', "Email account {$email} created.");
    }

    public function deleteAccount(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $account = DB::connection('openpanel')->table('email')
            ->where('id', $request->id)
            ->where('user', $this->username())
            ->first();

        if (!$account) {
            return back()->with('error', 'Account not found.');
        }

        DB::connection('openpanel')->table('email')->where('id', $request->id)->delete();

        return back()->with('success', 'Email account deleted.');
    }

    public function createForwarder(Request $request)
    {
        $request->validate([
            'source' => 'required|string',
            'destination' => 'required|email',
        ]);

        $username = $this->username();

        DB::connection('openpanel')->table('email_forwarders')->insert([
            'user' => $username,
            'source' => $request->source,
            'destination' => $request->destination,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Forwarder created.');
    }

    public function deleteForwarder(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        DB::connection('openpanel')->table('email_forwarders')
            ->where('id', $request->id)
            ->where('user', $this->username())
            ->delete();

        return back()->with('success', 'Forwarder deleted.');
    }

    public function createAutoresponder(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'subject' => 'required|string',
            'body' => 'required|string',
        ]);

        $username = $this->username();

        DB::connection('openpanel')->table('email_autoresponders')->insert([
            'user' => $username,
            'email' => $request->email,
            'subject' => $request->subject,
            'body' => $request->body,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Autoresponder created.');
    }

    public function deleteAutoresponder(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        DB::connection('openpanel')->table('email_autoresponders')
            ->where('id', $request->id)
            ->where('user', $this->username())
            ->delete();

        return back()->with('success', 'Autoresponder deleted.');
    }
}
