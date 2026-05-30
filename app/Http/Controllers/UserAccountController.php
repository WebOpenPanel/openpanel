<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use App\Models\User;
use App\Models\Package;
use Illuminate\Http\Request;

class UserAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = UserAccount::with(['user', 'package']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('username', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('suspended', $request->status === 'suspended' ? 'yes' : 'no');
        }

        $accounts = $query->latest()->paginate(20);
        $packages = Package::orderBy('name')->get();

        return view('accounts.index', compact('accounts', 'packages'));
    }

    public function create()
    {
        $packages = Package::orderBy('name')->get();
        return view('accounts.create', compact('packages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'domain' => 'required|string|max:255|unique:user_accounts,domain',
            'ip_address' => 'required|ip',
            'package_id' => 'required|exists:packages,id',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'user',
            'ip_address' => $request->ip_address,
        ]);

        $package = Package::find($request->package_id);

        $account = UserAccount::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'domain' => $request->domain,
            'ip_address' => $request->ip_address,
            'document_root' => "/home/{$request->username}/public_html",
            'shell_access' => $package->shell_access,
            'disk_quota_bytes' => $package->disk_space_mb * 1024 * 1024,
            'bandwidth_limit_bytes' => $package->bandwidth_mb * 1024 * 1024,
        ]);

        return redirect()->route('accounts.index')
            ->with('success', "Account '{$request->domain}' created successfully.");
    }

    public function show(UserAccount $account)
    {
        $account->load(['user', 'package', 'domains', 'dnsZones', 'mysqlDatabases', 'emailAccounts', 'ftpAccounts', 'sslCertificates', 'backups']);
        return view('accounts.show', compact('account'));
    }

    public function edit(UserAccount $account)
    {
        $packages = Package::orderBy('name')->get();
        return view('accounts.edit', compact('account', 'packages'));
    }

    public function update(Request $request, UserAccount $account)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'ip_address' => 'required|ip',
        ]);

        $account->update([
            'package_id' => $request->package_id,
            'ip_address' => $request->ip_address,
        ]);

        return redirect()->route('accounts.show', $account)
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(UserAccount $account)
    {
        $account->user()->delete();
        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    public function suspend(UserAccount $account)
    {
        $account->update([
            'suspended' => 'yes',
            'suspend_reason' => request('reason', 'Suspended by admin'),
        ]);

        return back()->with('success', "Account '{$account->domain}' has been suspended.");
    }

    public function unsuspend(UserAccount $account)
    {
        $account->update([
            'suspended' => 'no',
            'suspend_reason' => null,
        ]);

        return back()->with('success', "Account '{$account->domain}' has been unsuspended.");
    }
}
