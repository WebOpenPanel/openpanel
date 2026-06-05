<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\FtpService;
use App\Services\ShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    protected function legacyUserAccountId(): ?int
    {
        if (!Schema::hasTable('user_accounts')) {
            return null;
        }

        return DB::table('user_accounts')->where('username', $this->username())->value('id');
    }

    public function index()
    {
        $id = $this->accountId();
        $legacyId = $this->legacyUserAccountId();
        $query = DB::table('ftp_accounts');

        if ($id && Schema::hasColumn('ftp_accounts', 'account_id')) {
            $query->where(function ($q) use ($id, $legacyId) {
                $q->where('account_id', $id);
                if ($legacyId) {
                    $q->orWhere('user_account_id', $legacyId);
                }
            });
        } elseif ($legacyId) {
            $query->where('user_account_id', $legacyId);
        } else {
            return view('user-panel.ftp.index', [
                'accounts' => collect(),
                'ftpStatus' => FtpService::getStatus(),
            ]);
        }

        return view('user-panel.ftp.index', [
            'accounts' => $query->get(),
            'ftpStatus' => FtpService::getStatus(),
        ]);
    }

    public function create(Request $request)
    {
        $request->merge([
            'username' => $request->input('username', $request->input('ftp_user')),
            'directory' => $request->input('directory', $request->input('path', 'public_html')),
        ]);

        $data = $request->validate([
            'username' => 'required|string|alpha_dash',
            'password' => 'required|string|min:6',
            'directory' => 'required|string|max:255',
        ]);

        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $directory = trim(str_replace('\\', '/', $data['directory']));
        $directory = trim($directory, '/');
        if ($directory === '' || str_contains($directory, '..')) {
            return back()->with('error', 'Invalid FTP directory.');
        }

        $ftpUser = $this->username() . '_' . $data['username'];
        $homeDir = '/home/' . $this->username() . '/' . $directory;

        ShellService::exec("mkdir -p " . escapeshellarg($homeDir));
        FtpService::addUser($ftpUser, $data['password'], $this->username(), $homeDir);

        $insert = [
            'user_account_id' => $id,
            'username' => $ftpUser,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'home_directory' => $homeDir,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $legacyId = $this->legacyUserAccountId();
        if ($legacyId) {
            $insert['user_account_id'] = $legacyId;
        }
        if (Schema::hasColumn('ftp_accounts', 'account_id')) {
            $insert['account_id'] = $id;
        }

        DB::table('ftp_accounts')->insert($insert);

        return back()->with('success', "FTP account {$ftpUser} created.");
    }

    public function delete(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $legacyId = $this->legacyUserAccountId();
        $query = DB::table('ftp_accounts')->where('id', $request->id);
        if (Schema::hasColumn('ftp_accounts', 'account_id')) {
            $query->where(function ($q) use ($id, $legacyId) {
                $q->where('account_id', $id);
                if ($legacyId) {
                    $q->orWhere('user_account_id', $legacyId);
                }
            });
        } elseif ($legacyId) {
            $query->where('user_account_id', $legacyId);
        } else {
            return back()->with('error', 'Account not found.');
        }

        $ftp = $query->first();
        if ($ftp) {
            ShellService::exec("pure-pw userdel " . escapeshellarg($ftp->username) . " -f");
            ShellService::exec("pure-pw mkdb");
            DB::table('ftp_accounts')->where('id', $ftp->id)->delete();
        }
        return back()->with('success', 'FTP account deleted.');
    }
}
