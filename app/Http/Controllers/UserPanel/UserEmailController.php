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
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    protected function accountId(): ?int
    {
        $account = DB::table('accounts')->where('username', $this->username())->first();
        return $account?->id;
    }

    protected function domains()
    {
        $id = $this->accountId();
        return $id ? DB::table('domains')->where('user_account_id', $id)->pluck('domain') : collect();
    }

    public function index()
    {
        $id = $this->accountId();
        $accounts = $id ? DB::table('email_accounts')->where('user_account_id', $id)->get() : collect();
        $domains = $this->domains();
        return view('user-panel.email.index', compact('accounts', 'domains'));
    }

    public function forwarders()
    {
        $id = $this->accountId();
        $forwarders = $id ? DB::table('email_forwarders')->where('user_account_id', $id)->get() : collect();
        $domains = $this->domains();
        return view('user-panel.email.forwarders', compact('forwarders', 'domains'));
    }

    public function autoresponders()
    {
        $id = $this->accountId();
        $autoresponders = $id ? DB::table('email_autoresponders')->where('user_account_id', $id)->get() : collect();
        $domains = $this->domains();
        return view('user-panel.email.autoresponders', compact('autoresponders', 'domains'));
    }

    public function createAccount(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string|min:6',
            'quota' => 'integer|min:0',
        ]);

        $email = $request->email;
        $parts = explode('@', $email);
        $domain = $parts[1] ?? '';

        $id = $this->accountId();
        if (!$id) {
            return back()->with('error', 'Account not found.');
        }

        $domainCheck = DB::table('domains')
            ->where('user_account_id', $id)
            ->where('domain', $domain)
            ->exists();

        if (!$domainCheck) {
            return back()->with('error', 'Domain not found or not owned by you.');
        }

        $passwordHash = password_hash($request->password, PASSWORD_DEFAULT);
        DB::table('email_accounts')->insert([
            'user_account_id' => $id,
            'domain' => $domain,
            'email' => $email,
            'password_hash' => $passwordHash,
            'quota_mb' => $request->quota ?? 250,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "Email account {$email} created.");
    }

    public function deleteAccount(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        DB::table('email_accounts')->where('id', $request->id)->where('user_account_id', $id)->delete();
        return back()->with('success', 'Email account deleted.');
    }
}
