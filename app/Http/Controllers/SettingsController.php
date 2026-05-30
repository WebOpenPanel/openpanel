<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        Auth::user()->update(['password' => $request->password]);
        return back()->with('success', 'Password changed successfully.');
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . Auth::id(),
            'theme' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:10',
        ]);

        Auth::user()->update($request->only(['email', 'theme', 'language']));
        return back()->with('success', 'Profile updated successfully.');
    }
}
