<?php

namespace App\Http\Controllers;

use App\Services\ClusterService;
use Illuminate\Http\Request;

class ClusterController extends Controller
{
    public function index()
    {
        $configured = ClusterService::isConfigured();
        $servers = $configured ? ClusterService::getServers() : [];
        $config = $configured ? ClusterService::getConfig() : [];
        return view('cluster.index', compact('configured', 'servers', 'config'));
    }

    public function init()
    {
        ClusterService::initCluster();
        return back()->with('success', 'Cluster initialized.');
    }

    public function addServer(Request $request)
    {
        $request->validate(['name' => 'required|string', 'ip' => 'required|ip', 'apikey' => 'required|string']);
        ClusterService::addServer($request->name, $request->ip, $request->apikey);
        return back()->with('success', "Server '{$request->name}' added.");
    }

    public function removeServer(int $id)
    {
        ClusterService::removeServer($id);
        return back()->with('success', 'Server removed.');
    }

    public function config()
    {
        $config = ClusterService::getConfig();
        return view('cluster.config', compact('config'));
    }

    public function saveConfig(Request $request)
    {
        foreach ($request->except(['_token']) as $key => $value) {
            ClusterService::setConfig($key, $value);
        }
        return back()->with('success', 'Config saved.');
    }
}
