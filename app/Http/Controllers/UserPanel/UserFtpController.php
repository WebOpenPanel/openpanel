<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserFtpController extends Controller
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

    public function index()
    {
        $id = $this->accountId();
        $accounts = $id ? DB::table('ftp_accounts')->where('user_account_id', $id)->get() : collect();
        return view('user-panel.ftp.index', compact('accounts'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'username' => 'required|string|alpha_dash',
            'password' => 'required|string|min:6',
            'directory' => 'required|string',
        ]);

        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $ftpUser = $this->username() . '_' . $request->username;
        $homeDir = '/home/' . $this->username() . '/' . ltrim($request->directory, '/');

        ShellService::exec("mkdir -p " . escapeshellarg($homeDir));
        ShellService::exec("pure-pw useradd " . escapeshellarg($ftpUser) . " -u " . escapeshellarg($this->username()) . " -d " . escapeshellarg($homeDir) . " <<EOF\n" . $request->password . "\n" . $request->password . "\nEOF");
        ShellService::exec("pure-pw mkdb");

        $passwordHash = password_hash($request->password, PASSWORD_DEFAULT);
        DB::table('ftp_accounts')->insert([
            'user_account_id' => $id,
            'username' => $ftpUser,
            'password_hash' => $passwordHash,
            'home_directory' => $homeDir,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "FTP account {$ftpUser} created.");
    }

    public function delete(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $ftp = DB::table('ftp_accounts')->where('id', $request->id)->where('user_account_id', $id)->first();
        if ($ftp) {
            ShellService::exec("pure-pw userdel " . escapeshellarg($ftp->username) . " -f");
            ShellService::exec("pure-pw mkdb");
            DB::table('ftp_accounts')->where('id', $ftp->id)->delete();
        }
        return back()->with('success', 'FTP account deleted.');
    }
}
