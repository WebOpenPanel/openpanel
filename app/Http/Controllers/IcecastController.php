<?php

namespace App\Http\Controllers;

use App\Services\IcecastService;
use Illuminate\Http\Request;

class IcecastController extends Controller
{
    public function index()
    {
        $installed = IcecastService::isInstalled();
        $options = $installed ? IcecastService::getOptions() : [];
        $servers = $installed ? IcecastService::getServers() : [];
        return view('icecast.index', compact('installed', 'options', 'servers'));
    }

    public function install()
    {
        $result = IcecastService::install();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function saveOptions(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'port_range_min' => 'required|integer',
            'port_range_max' => 'required|integer',
        ]);
        $data['enabled'] = $request->boolean('enabled') ? 1 : 0;
        IcecastService::saveOptions($data);
        return back()->with('success', 'Icecast options saved.');
    }

    public function addServer(Request $request)
    {
        $data = $request->validate([
            'user' => 'required|string',
            'port' => 'required|integer',
            'listens' => 'nullable|integer',
            'sources' => 'nullable|integer',
            'ip' => 'nullable|string',
        ]);
        $result = IcecastService::addServer($data);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function removeServer(int $port)
    {
        $result = IcecastService::removeServer($port);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function start(int $port)
    {
        IcecastService::startServer($port);
        return back()->with('success', "Icecast on port {$port} started.");
    }

    public function stop(int $port)
    {
        IcecastService::stopServer($port);
        return back()->with('success', "Icecast on port {$port} stopped.");
    }

    public function restart(int $port)
    {
        IcecastService::restartServer($port);
        return back()->with('success', "Icecast on port {$port} restarted.");
    }
}
