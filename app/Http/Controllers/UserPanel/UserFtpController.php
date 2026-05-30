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

    public function index()
    {
        $accounts = DB::connection('openpanel')->table('ftp')
            ->where('user', $this->username())
            ->get();

        return view('user-panel.ftp.index', compact('accounts'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'ftp_user' => 'required|string|regex:/^[a-zA-Z0-9_\-]+$/',
            'password' => 'required|string|min:6',
            'path' => 'nullable|string',
        ]);

        $username = $this->username();
        $ftpUser = $request->ftp_user;
        $password = $request->password;
        $homePath = '/home/' . $username;
        $ftpPath = $homePath . '/' . ltrim($request->path ?? 'public_html', '/');

        if (!str_starts_with(realpath($ftpPath) ?: $ftpPath, $homePath)) {
            return back()->with('error', 'Invalid path.');
        }

        ShellService::exec("mkdir -p " . escapeshellarg($ftpPath));
        ShellService::exec("(echo " . escapeshellarg($password) . "; echo " . escapeshellarg($password) . ") | pure-pw useradd " . escapeshellarg($ftpUser . '@' . $username) . " -u " . escapeshellarg($username) . " -g " . escapeshellarg($username) . " -d " . escapeshellarg($ftpPath) . " -m 2>&1");

        DB::connection('openpanel')->table('ftp')->insert([
            'user' => $username,
            'ftp_user' => $ftpUser,
            'path' => $ftpPath,
            'created_at' => now(),
        ]);

        return back()->with('success', "FTP account {$ftpUser}@{$username} created.");
    }

    public function delete(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $username = $this->username();
        $account = DB::connection('openpanel')->table('ftp')
            ->where('id', $request->id)
            ->where('user', $username)
            ->first();

        if (!$account) {
            return back()->with('error', 'FTP account not found.');
        }

        ShellService::exec("pure-pw userdel " . escapeshellarg($account->ftp_user . '@' . $username) . " -m 2>&1");
        DB::connection('openpanel')->table('ftp')->where('id', $request->id)->delete();

        return back()->with('success', 'FTP account deleted.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'password' => 'required|string|min:6',
        ]);

        $username = $this->username();
        $account = DB::connection('openpanel')->table('ftp')
            ->where('id', $request->id)
            ->where('user', $username)
            ->first();

        if (!$account) {
            return back()->with('error', 'FTP account not found.');
        }

        ShellService::exec("(echo " . escapeshellarg($request->password) . "; echo " . escapeshellarg($request->password) . ") | pure-pw passwd " . escapeshellarg($account->ftp_user . '@' . $username) . " -m 2>&1");

        return back()->with('success', 'FTP password changed.');
    }
}
