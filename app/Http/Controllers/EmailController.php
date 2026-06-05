<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use App\Models\EmailAutoresponder;
use App\Models\UserAccount;
use App\Services\MailService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailAccount::query();
        if ($request->filled('search')) {
            $query->where('email', 'like', "%{$request->search}%");
        }
        $emailAccounts = $query->latest()->paginate(20);
        $accounts = DB::table('accounts')->where('status', 'active')->orderBy('domain')->get();
        $mailStatus = MailService::getMailServerStatus();
        return view('email.index', compact('emailAccounts', 'accounts', 'mailStatus'));
    }

    public function createAccount(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer',
            'email_prefix' => 'required|string|max:64',
            'password' => 'required|string|min:8|confirmed',
            'quota_mb' => 'nullable|integer|min:0',
        ]);
        $account = DB::table('accounts')->where('id', $request->account_id)->where('status', 'active')->first();
        if (!$account) {
            return back()->with('error', 'Hosting account not found.');
        }

        $result = (new EmailService())->createMailbox($account, $account->domain, $request->email_prefix, $request->password, $request->quota_mb ?? 250);
        return back()->with('success', "Email account '{$result['email']}' created.");
    }

    public function destroyAccount(EmailAccount $emailAccount)
    {
        (new EmailService())->deleteMailbox($emailAccount->email);
        return back()->with('success', 'Email account deleted.');
    }

    public function forwarders(Request $request)
    {
        $query = EmailForwarder::with('userAccount');
        if ($request->filled('search')) {
            $query->where('source_email', 'like', "%{$request->search}%");
        }
        $forwarders = $query->latest()->paginate(20);
        $accounts = UserAccount::where('suspended', 'no')->orderBy('domain')->get();
        return view('email.forwarders', compact('forwarders', 'accounts'));
    }

    public function createForwarder(Request $request)
    {
        $request->validate([
            'user_account_id' => 'required|exists:user_accounts,id',
            'source_prefix' => 'required|string',
            'destination_email' => 'required|email',
        ]);
        $account = UserAccount::find($request->user_account_id);
        EmailForwarder::create([
            'user_account_id' => $account->id,
            'source_email' => $request->source_prefix . '@' . $account->domain,
            'destination_email' => $request->destination_email,
        ]);
        return back()->with('success', 'Email forwarder created.');
    }

    public function destroyForwarder(EmailForwarder $forwarder)
    {
        $forwarder->delete();
        return back()->with('success', 'Email forwarder deleted.');
    }

    public function autoresponders(Request $request)
    {
        $query = EmailAutoresponder::with('userAccount');
        $autoresponders = $query->latest()->paginate(20);
        $accounts = UserAccount::where('suspended', 'no')->orderBy('domain')->get();
        return view('email.autoresponders', compact('autoresponders', 'accounts'));
    }

    public function createAutoresponder(Request $request)
    {
        $request->validate([
            'user_account_id' => 'required|exists:user_accounts,id',
            'email_prefix' => 'required|string',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
        $account = UserAccount::find($request->user_account_id);
        EmailAutoresponder::create([
            'user_account_id' => $account->id,
            'email' => $request->email_prefix . '@' . $account->domain,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);
        return back()->with('success', 'Autoresponder created.');
    }

    public function destroyAutoresponder(EmailAutoresponder $autoresponder)
    {
        $autoresponder->delete();
        return back()->with('success', 'Autoresponder deleted.');
    }

    public function mailQueue()
    {
        $queue = MailService::getMailQueue();
        $count = MailService::getMailQueueCount();
        return view('email.queue', compact('queue', 'count'));
    }

    public function flushQueue()
    {
        $output = MailService::flushMailQueue();
        return back()->with('output', $output)->with('success', 'Mail queue flushed.');
    }

    public function deleteQueue()
    {
        $output = MailService::deleteMailQueue();
        return back()->with('output', $output)->with('success', 'Mail queue deleted.');
    }

    public function dkim()
    {
        return view('email.dkim');
    }

    public function addPipe(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'domain' => 'required|string',
            'username' => 'required|string',
            'php_path' => 'nullable|string',
            'script_path' => 'required|string',
        ]);
        MailService::pipeToScriptAdd(
            $request->email,
            $request->domain,
            $request->username,
            $request->php_path ?? '/usr/local/bin/php',
            $request->script_path
        );
        return back()->with('success', 'Pipe added.');
    }

    public function removePipe(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        MailService::pipeToScriptRemove($request->email);
        return back()->with('success', 'Pipe removed.');
    }

    public function mxEntry()
    {
        return view('email.mx');
    }

    public function saveMx(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'priority' => 'required|integer',
            'exchange' => 'required|string',
        ]);
        MailService::setMxEntry($request->domain, $request->priority, $request->exchange);
        return back()->with('success', 'MX record updated.');
    }

    public function postfixConfig()
    {
        $conf = MailService::getPostfixMainConf();
        return view('email.postfix-config', compact('conf'));
    }

    public function savePostfixConfig(Request $request)
    {
        $request->validate(['content' => 'required|string']);
        MailService::savePostfixMainConf($request->content);
        return back()->with('success', 'Postfix config saved.');
    }

    public function dovecotConfig()
    {
        $conf = MailService::getDovecotConf();
        return view('email.dovecot-config', compact('conf'));
    }

    public function saveDovecotConfig(Request $request)
    {
        $request->validate(['content' => 'required|string']);
        MailService::saveDovecotConf($request->content);
        return back()->with('success', 'Dovecot config saved.');
    }

    public function mailLog(Request $request)
    {
        $lines = (int) $request->get('lines', 100);
        $log = MailService::getMailLog($lines);
        return view('email.mail-log', compact('log'));
    }

    public function explorer(Request $request)
    {
        $directory = $request->get('path', '/var/vmail');
        $items = MailService::getMailExplorer($directory);
        return view('email.explorer', compact('items', 'directory'));
    }
}
