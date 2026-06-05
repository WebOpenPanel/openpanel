<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
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
        $account = DB::table('accounts')->where('username', $this->username())->first();
        return $account ? collect([$account->domain]) : collect();
    }

    public function index()
    {
        $id = $this->accountId();
        $accounts = $id ? DB::table('email_accounts')->where('account_id', $id)->whereNull('deleted_at')->get() : collect();
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
            'local_part' => 'required|string',
            'domain' => 'required|string',
            'password' => 'required|string|min:6',
            'quota' => 'integer|min:0',
        ]);

        $id = $this->accountId();
        if (!$id) {
            return back()->with('error', 'Account not found.');
        }

        $account = DB::table('accounts')->where('id', $id)->first();
        if (!$account || $account->domain !== $request->domain) {
            return back()->with('error', 'Domain not found or not owned by you.');
        }

        $result = (new EmailService())->createMailbox($account, $request->domain, $request->local_part, $request->password, $request->quota ?? 250);

        return back()->with('success', "Email account {$result['email']} created.");
    }

    public function deleteAccount(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $mailbox = DB::table('email_accounts')->where('id', $request->id)->where('account_id', $id)->whereNull('deleted_at')->first();
        if (!$mailbox) return back()->with('error', 'Mailbox not found.');

        (new EmailService())->deleteMailbox($mailbox->email);
        return back()->with('success', 'Email account deleted.');
    }
}
