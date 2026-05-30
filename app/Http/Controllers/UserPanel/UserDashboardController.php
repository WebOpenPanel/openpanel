<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Support\Facades\DB;

class UserDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $username = $user->username;

        $diskUsed = trim(ShellService::exec("du -sm /home/{$username} 2>/dev/null | cut -f1") ?: '0');
        $diskQuota = trim(ShellService::exec("quota -u {$username} 2>/dev/null | tail -1 | awk '{print $2}'") ?: '0');

        $domains = DB::connection('openpanel')->table('domains')->where('user', $username)->count();
        $databases = DB::connection('openpanel')->table('mysql_db')->where('user', $username)->count();
        $emailAccounts = DB::connection('openpanel')->table('email')->where('user', $username)->count();
        $ftpAccounts = DB::connection('openpanel')->table('ftp')->where('user', $username)->count();

        $package = DB::connection('openpanel')->table('user')->where('username', $username)->first();

        return view('user-panel.dashboard', compact(
            'username', 'diskUsed', 'diskQuota',
            'domains', 'databases', 'emailAccounts', 'ftpAccounts', 'package'
        ));
    }
}
