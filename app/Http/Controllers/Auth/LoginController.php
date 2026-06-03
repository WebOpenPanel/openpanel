<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LinuxAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof LinuxAuthUser && $user->isAdmin()) {
                return redirect()->route('dashboard');
            }
            if ($user instanceof LinuxAuthUser && $user->isReseller()) {
                return redirect()->route('reseller.dashboard');
            }
            return redirect()->route('user.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $credentials['username'];
        $port = $request->getPort();

        $linuxUser = LinuxAuthUser::findByUsername($username);
        if (!$linuxUser) {
            return back()->withErrors([
                'username' => 'User does not exist on this server.',
            ])->onlyInput('username');
        }

        if (!LinuxAuthUser::verifyPassword($username, $credentials['password'])) {
            return back()->withErrors([
                'username' => 'Invalid password.',
            ])->onlyInput('username');
        }

        $adminPorts = [2086, 2087];
        if ($linuxUser->isAdmin() && !in_array($port, $adminPorts)) {
            return redirect('https://' . $request->getHost() . ':2087/login')
                ->with('status', 'Admin users must login on port 2087.');
        }

        if (!$linuxUser->isAdmin() && !$linuxUser->isReseller() && in_array($port, $adminPorts)) {
            return redirect('https://' . $request->getHost() . ':2083/login')
                ->with('status', 'Regular users must login on port 2083.');
        }

        if ($linuxUser->isReseller() && in_array($port, $adminPorts)) {
            return redirect('https://' . $request->getHost() . ':2083/login')
                ->with('status', 'Resellers must login on port 2083.');
        }

        Auth::login($linuxUser);
        $request->session()->regenerate();

        if ($linuxUser->isAdmin()) {
            return redirect()->intended(route('dashboard'));
        }

        if ($linuxUser->isReseller()) {
            return redirect()->intended(route('reseller.dashboard'));
        }

        return redirect()->intended(route('user.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
