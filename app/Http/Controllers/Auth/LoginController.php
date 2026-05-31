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

        Auth::login($linuxUser);
        $request->session()->regenerate();

        if ($linuxUser->isAdmin()) {
            return redirect()->intended(route('dashboard'));
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
