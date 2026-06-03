<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\AccountService;
use Illuminate\Http\Request;

class UserAccountController extends Controller
{
    protected AccountService $accounts;

    public function __construct(AccountService $accounts)
    {
        $this->accounts = $accounts;
    }

    public function index(Request $request)
    {
        $users = $this->accounts->listUsers();
        $accounts = [];

        foreach ($users as $username) {
            if ($username === 'root') continue;
            $info = $this->accounts->getUser($username);
            if ($info) {
                $accounts[] = $info;
            }
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $accounts = array_filter($accounts, fn($a) =>
                str_contains($a['username'] ?? '', $search) ||
                str_contains($a['domain'] ?? '', $search)
            );
        }

        $page = $request->input('page', 1);
        $perPage = 20;
        $total = count($accounts);
        $paged = array_slice($accounts, ($page - 1) * $perPage, $perPage);
        $accounts = new \Illuminate\Pagination\LengthAwarePaginator($paged, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('accounts.index', compact('accounts'));
    }

    public function create()
    {
        $packages = Package::all();
        return view('accounts.create', compact('packages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:32',
            'password' => 'required|string|min:8|confirmed',
            'domain' => 'required|string|max:255',
            'ip_address' => 'nullable|ip',
            'email' => 'nullable|email',
            'package' => 'nullable|string',
            'disk_limit' => 'nullable|integer|min:100',
            'bandwidth_limit' => 'nullable|integer|min:100',
        ]);

        try {
            $result = $this->accounts->create($request->all());
            return redirect()->route('accounts.index')
                ->with('success', $result['message']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(string $username)
    {
        $account = $this->accounts->getUser($username);
        if (!$account) {
            abort(404, 'Account not found.');
        }

        $home = "/home/{$username}";
        $diskUsed = 0;
        $result = app(\Illuminate\Process\Factory::class)->run("du -sm {$home} 2>/dev/null | awk '{print $1}'");
        if ($result->successful()) {
            $diskUsed = (int) trim($result->output());
        }

        return view('accounts.show', compact('account', 'username', 'home', 'diskUsed'));
    }

    public function edit(string $username)
    {
        $account = $this->accounts->getUser($username);
        if (!$account) {
            abort(404, 'Account not found.');
        }
        $packages = Package::all();
        return view('accounts.edit', compact('account', 'username', 'packages'));
    }

    public function update(Request $request, string $username)
    {
        $account = $this->accounts->getUser($username);
        if (!$account) {
            abort(404, 'Account not found.');
        }

        if ($request->filled('new_password')) {
            $request->validate([
                'new_password' => 'required|string|min:8|confirmed',
            ]);
            $this->accounts->changePassword($username, $request->new_password);
            return back()->with('success', 'Password changed.');
        }

        return back()->with('info', 'No changes submitted.');
    }

    public function destroy(string $username)
    {
        try {
            $result = $this->accounts->delete($username);
            return redirect()->route('accounts.index')
                ->with('success', $result['message']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function suspend(string $username)
    {
        try {
            $result = $this->accounts->suspend($username);
            return back()->with('success', $result['message']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function unsuspend(string $username)
    {
        try {
            $result = $this->accounts->unsuspend($username);
            return back()->with('success', $result['message']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
