<?php

namespace App\Http\Controllers;

use App\Services\FtpService;
use Illuminate\Http\Request;

class FtpController extends Controller
{
    public function index()
    {
        $users = FtpService::getUserList();
        $status = FtpService::getStatus();
        return view('ftp.index', compact('users', 'status'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:64',
            'password' => 'required|string|min:6',
            'system_user' => 'required|string|max:32',
            'path' => 'required|string',
        ]);
        FtpService::addUser($request->username, $request->password, $request->system_user, $request->path);
        return back()->with('success', "FTP user '{$request->username}' created successfully.");
    }

    public function destroy(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        FtpService::deleteUser($request->username);
        return back()->with('success', 'FTP user deleted successfully.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);
        FtpService::changePassword($request->username, $request->password);
        return back()->with('success', 'Password changed successfully.');
    }

    public function sessions()
    {
        $sessions = FtpService::getActiveSessions();
        return view('ftp.sessions', compact('sessions'));
    }

    public function killSession(Request $request)
    {
        $request->validate(['pid' => 'required|integer']);
        FtpService::killSession($request->pid);
        return back()->with('success', 'Session terminated.');
    }

    public function configuration()
    {
        $conf = FtpService::getConf();
        return view('ftp.config', compact('conf'));
    }

    public function saveConfig(Request $request)
    {
        $request->validate(['content' => 'required|string']);
        FtpService::saveConf($request->content);
        return back()->with('success', 'FTP configuration saved.');
    }

    public function restart()
    {
        FtpService::restart();
        return back()->with('success', 'FTP server restarted.');
    }
}
