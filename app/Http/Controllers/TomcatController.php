<?php

namespace App\Http\Controllers;

use App\Services\TomcatService;
use Illuminate\Http\Request;

class TomcatController extends Controller
{
    public function index()
    {
        $installed = TomcatService::isInstalled();
        $status = $installed ? TomcatService::status() : ['running' => false];
        $apps = $installed ? TomcatService::getApps() : [];
        $users = $installed ? TomcatService::getUsers() : [];
        return view('tomcat.index', compact('installed', 'status', 'apps', 'users'));
    }

    public function install()
    {
        TomcatService::install();
        return back()->with('success', 'Tomcat installed.');
    }

    public function uninstall()
    {
        TomcatService::uninstall();
        return back()->with('success', 'Tomcat uninstalled.');
    }

    public function start() { return back()->with('output', TomcatService::start()); }
    public function stop() { return back()->with('output', TomcatService::stop()); }
    public function restart() { return back()->with('output', TomcatService::restart()); }

    public function addUser(Request $request)
    {
        $request->validate(['username' => 'required|string', 'password' => 'required|string', 'roles' => 'nullable|string']);
        TomcatService::addUser($request->username, $request->password, $request->roles ?? 'manager-gui');
        return back()->with('success', "User '{$request->username}' added.");
    }

    public function deleteUser(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        TomcatService::deleteUser($request->username);
        return back()->with('success', "User '{$request->username}' deleted.");
    }

    public function deploy(Request $request)
    {
        $request->validate(['war_path' => 'required|string', 'context' => 'nullable|string']);
        TomcatService::deployWar($request->war_path, $request->context);
        return back()->with('success', 'WAR deployed.');
    }

    public function undeploy(Request $request)
    {
        $request->validate(['app_name' => 'required|string']);
        TomcatService::undeploy($request->app_name);
        return back()->with('success', 'App undeployed.');
    }
}
