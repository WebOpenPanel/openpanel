<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\LinuxAuthUser;
use App\Models\UserAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResellerDashboardController extends Controller
{
    public function index()
    {
        $reseller = Auth::user();
        $managedUsers = $this->getManagedUsers($reseller);
        $totalAccounts = $managedUsers->count();
        $totalDiskUsed = $managedUsers->sum('disk_usage_bytes');
        $totalDiskQuota = $managedUsers->sum('disk_quota_bytes');
        $suspendedAccounts = $managedUsers->where('suspended', 'yes')->count();

        return view('reseller.dashboard', compact(
            'managedUsers', 'totalAccounts', 'totalDiskUsed',
            'totalDiskQuota', 'suspendedAccounts'
        ));
    }

    public function accounts()
    {
        $reseller = Auth::user();
        $accounts = $this->getManagedUsers($reseller);
        return view('reseller.accounts', compact('accounts'));
    }

    public function createAccount()
    {
        $packages = DB::table('packages')->get();
        return view('reseller.create-account', compact('packages'));
    }

    public function storeAccount(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:32|regex:/^[a-z][a-z0-9_]*$/',
            'password' => 'required|string|min:8',
            'domain' => 'required|string|max:255',
            'email' => 'required|email',
            'package_id' => 'required|exists:packages,id',
        ]);

        $reseller = Auth::user();
        $username = $request->username;
        $password = $request->password;
        $domain = $request->domain;

        $existing = LinuxAuthUser::findByUsername($username);
        if ($existing) {
            return back()->with('error', 'Username already exists.')->withInput();
        }

        $result = \App\Services\ShellService::exec(
            "useradd -m -d /home/{$username} -s /bin/bash {$username} 2>&1"
        );
        if (!empty($result) && str_contains($result, 'error')) {
            return back()->with('error', 'Failed to create user: ' . $result)->withInput();
        }

        \App\Services\ShellService::exec("echo '{$username}:{$password}' | chpasswd 2>&1");

        $package = DB::table('packages')->where('id', $request->package_id)->first();
        $serverIp = trim(\App\Services\ShellService::exec("hostname -I 2>/dev/null | awk '{print \$1}'"));

        UserAccount::create([
            'user_id' => 0,
            'package_id' => $request->package_id,
            'domain' => $domain,
            'ip_address' => $serverIp,
            'document_root' => "/home/{$username}/public_html",
            'shell' => '/bin/bash',
            'shell_access' => false,
            'disk_quota_bytes' => ($package->disk ?? 0) * 1024 * 1024,
            'bandwidth_limit_bytes' => ($package->bandwidth ?? 0) * 1024 * 1024,
        ]);

        \App\Services\ShellService::exec("mkdir -p /home/{$username}/public_html");
        \App\Services\ShellService::exec("chown -R {$username}:{$username} /home/{$username}");

        return redirect()->route('reseller.accounts')
            ->with('success', "Account '{$username}' created.");
    }

    protected function getManagedUsers(LinuxAuthUser $reseller): \Illuminate\Support\Collection
    {
        $accounts = UserAccount::with('package')->get();
        return $accounts->filter(function ($account) use ($reseller) {
            $owner = $account->user;
            if (!$owner) return false;
            return $reseller->canManageUser($owner->username ?? '');
        })->values();
    }
}
