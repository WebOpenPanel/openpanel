<?php

namespace App\Http\Controllers;

use App\Services\NodejsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class NodejsController extends Controller
{
    public function index()
    {
        $installed = NodejsService::isInstalled();
        $versions = $installed ? NodejsService::listInstalled() : [];
        $config = NodejsService::getUserConfig();
        return view('nodejs.index', compact('installed', 'versions', 'config'));
    }

    public function apps()
    {
        $data = NodejsService::listApps();
        return view('nodejs.apps', ['apps' => $data['list'] ?? [], 'domains' => $data['domains'] ?? [], 'users' => $data['users'] ?? []]);
    }

    public function install()
    {
        $result = NodejsService::install();
        return back()->with($result['result'] === 'success' ? 'success' : 'error', json_encode($result));
    }

    public function uninstall()
    {
        $result = NodejsService::uninstall();
        return back()->with('success', 'Node.js Manager uninstalled.');
    }

    public function installVersion(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $output = NodejsService::installVersion($request->version);
        return back()->with('output', $output)->with('success', "Node.js {$request->version} installation started.");
    }

    public function uninstallVersion(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $output = NodejsService::uninstallVersion($request->version);
        return back()->with('output', $output);
    }

    public function setDefault(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        NodejsService::setDefault($request->version);
        return back()->with('success', "Default Node.js version set to {$request->version}.");
    }

    public function saveApp(Request $request)
    {
        $request->validate(['app' => 'required|json', 'type' => 'required|in:new,edit']);
        $appData = json_decode($request->app, true);
        $result = NodejsService::saveApp($appData, $request->type, $request->key_id);
        return Response::json($result);
    }

    public function deleteApp(Request $request)
    {
        $request->validate(['key_id' => 'required|string']);
        $result = NodejsService::deleteApp($request->key_id);
        return Response::json($result);
    }

    public function appInfo(Request $request)
    {
        $request->validate(['key_id' => 'required|string']);
        $result = NodejsService::getAppInfo($request->key_id);
        return Response::json($result);
    }

    public function handleStatus(Request $request)
    {
        $request->validate(['action' => 'required|in:start,stop,restart', 'app_name' => 'required|string']);
        NodejsService::handleStatus($request->action, $request->app_name);
        return back()->with('success', "App {$request->action} executed.");
    }

    public function npmInstall(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        $result = NodejsService::npmInstall($request->key);
        return Response::json($result);
    }

    public function npmCommand(Request $request)
    {
        $request->validate(['key' => 'required|string', 'command' => 'required|string']);
        $result = NodejsService::npmCommand($request->key, $request->command);
        return Response::json($result);
    }

    public function appLog(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        $result = NodejsService::getAppLog($request->key, (int) ($request->lines ?? 20));
        return Response::json($result);
    }

    public function npmInstallLog(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        $result = NodejsService::getNpmInstallLog($request->key);
        return Response::json($result);
    }

    public function saveUserConfig(Request $request)
    {
        NodejsService::saveUserConfig($request->all());
        return back()->with('success', 'Node.js config saved.');
    }

    public function listAvailable()
    {
        $output = NodejsService::listAvailable();
        return Response::json(['output' => $output]);
    }
}
