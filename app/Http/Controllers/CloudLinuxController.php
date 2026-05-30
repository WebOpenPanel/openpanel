<?php

namespace App\Http\Controllers;

use App\Services\CloudLinuxService;
use Illuminate\Http\Request;

class CloudLinuxController extends Controller
{
    public function index()
    {
        $installed = CloudLinuxService::isInstalled();
        $openvz = CloudLinuxService::isOpenVz();
        $cageStatus = $installed ? CloudLinuxService::getCageFsStatus() : '';
        $cageMode = $installed ? CloudLinuxService::getCageFsMode() : '';
        $lveLimits = $installed ? CloudLinuxService::getLveLimits() : '';
        $phpVersions = $installed ? CloudLinuxService::getPhpSelectorVersions() : '';
        return view('cloudlinux.index', compact('installed', 'openvz', 'cageStatus', 'cageMode', 'lveLimits', 'phpVersions'));
    }

    public function enableCageFs() { return back()->with('output', CloudLinuxService::enableCageFs()); }
    public function disableCageFs() { return back()->with('output', CloudLinuxService::disableCageFs()); }
    public function updateCageFs() { return back()->with('output', CloudLinuxService::updateCageFs()); }
    public function enableAll() { return back()->with('output', CloudLinuxService::enableAll()); }
    public function disableAll() { return back()->with('output', CloudLinuxService::disableAll()); }
    public function listEnabled() { return back()->with('output', CloudLinuxService::listEnabled()); }
    public function listDisabled() { return back()->with('output', CloudLinuxService::listDisabled()); }

    public function enableUser(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        return back()->with('output', CloudLinuxService::enableUser($request->username));
    }

    public function disableUser(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        return back()->with('output', CloudLinuxService::disableUser($request->username));
    }

    public function setLveUser(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        return back()->with('output', CloudLinuxService::setLveUser($request->username, $request->all()));
    }

    public function getUserPhp(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        return back()->with('output', CloudLinuxService::getUserPhpVersion($request->username));
    }

    public function setUserPhp(Request $request)
    {
        $request->validate(['username' => 'required|string', 'version' => 'required|string']);
        return back()->with('output', CloudLinuxService::setUserPhpVersion($request->username, $request->version));
    }
}
