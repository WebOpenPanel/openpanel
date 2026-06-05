<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserDashboardController extends Controller
{
    public function index()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $username = $user->username;

        $diskUsed = (int) trim(ShellService::exec("du -sm /home/{$username} 2>/dev/null | cut -f1") ?: '0');

        $account = DB::table('accounts')->where('username', $username)->first();
        $accountId = $account?->id;
        $diskQuota = $account?->disk_limit ?? 0;
        $package = $account;

        $domains = $accountId ? DB::table('domains')->where('user_account_id', $accountId)->count() : 0;
        $databaseColumn = Schema::hasColumn('mysql_databases', 'account_id') ? 'account_id' : 'user_account_id';
        $databases = $accountId ? DB::table('mysql_databases')->where($databaseColumn, $accountId)->count() : 0;
        $emailAccounts = $accountId ? DB::table('email_accounts')->where('user_account_id', $accountId)->count() : 0;
        $ftpAccounts = $accountId ? DB::table('ftp_accounts')->where('user_account_id', $accountId)->count() : 0;

        return view('user-panel.dashboard', compact(
            'username', 'diskUsed', 'diskQuota', 'account', 'package',
            'domains', 'databases', 'emailAccounts', 'ftpAccounts'
        ));
    }
}
