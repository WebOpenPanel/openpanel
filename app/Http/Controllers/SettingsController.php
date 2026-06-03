<?php

namespace App\Http\Controllers;

use App\Models\LinuxAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Process\Factory as ProcessFactory;

class SettingsController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $user = Auth::user();
        if (!$user instanceof LinuxAuthUser) {
            abort(403);
        }

        $userInfo = [
            'username' => $user->username,
            'uid' => $user->uid,
            'home' => $user->home,
            'shell' => $user->shell,
            'role' => $user->role,
        ];

        $hostname = trim($this->process()->run('hostname -f')->output() ?: 'unknown');
        $serverIp = trim($this->process()->run("hostname -I | awk '{print \$1}'")->output() ?: '127.0.0.1');

        return view('settings.index', compact('userInfo', 'hostname', 'serverIp'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();
        if (!$user instanceof LinuxAuthUser) {
            abort(403);
        }

        if (!LinuxAuthUser::verifyPassword($user->username, $request->current_password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $result = $this->process()->run("echo '{$user->username}:{$request->password}' | chpasswd 2>&1");
        if ($result->failed()) {
            return back()->withErrors(['password' => 'Failed to change password: ' . $result->errorOutput()]);
        }

        return back()->with('success', 'Password changed successfully.');
    }

    public function changeTheme(Request $request)
    {
        $request->validate([
            'theme' => 'required|in:light,dark,system',
        ]);

        session(['theme' => $request->theme]);
        return back()->with('success', 'Theme updated.');
    }

    public function changeLanguage(Request $request)
    {
        $request->validate([
            'language' => 'required|string|max:5',
        ]);

        session(['locale' => $request->language]);
        app()->setLocale($request->language);
        return back()->with('success', 'Language updated.');
    }
}
