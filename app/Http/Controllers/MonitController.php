<?php

namespace App\Http\Controllers;

use App\Services\MonitService;
use Illuminate\Http\Request;

class MonitController extends Controller
{
    public function index()
    {
        $installed = MonitService::isInstalled();
        $status = $installed ? MonitService::status() : '';
        $configs = $installed ? MonitService::listServiceConfigs() : [];
        return view('monit.index', compact('installed', 'status', 'configs'));
    }

    public function install() { return back()->with('output', MonitService::install()); }
    public function uninstall() { return back()->with('output', MonitService::uninstall()); }
    public function start() { return back()->with('output', MonitService::start()); }
    public function stop() { return back()->with('output', MonitService::stop()); }
    public function restart() { return back()->with('output', MonitService::restart()); }
    public function summary() { return back()->with('output', MonitService::summary()); }

    public function editConfig(Request $request)
    {
        $request->validate(['file' => 'required|string']);
        $file = $request->input('file');
        $content = MonitService::getServiceConfig($file);
        return view('monit.edit', compact('file', 'content'));
    }

    public function saveConfig(Request $request)
    {
        $request->validate(['file' => 'required|string', 'content' => 'required|string']);
        MonitService::saveServiceConfig($request->input('file'), $request->input('content'));
        return back()->with('success', 'Config saved.');
    }

    public function deleteConfig(Request $request)
    {
        $request->validate(['file' => 'required|string']);
        MonitService::deleteServiceConfig($request->input('file'));
        return back()->with('success', 'Config deleted.');
    }

    public function monitor(Request $request)
    {
        $request->validate(['service' => 'required|string']);
        return back()->with('output', MonitService::monitorService($request->input('service')));
    }

    public function unmonitor(Request $request)
    {
        $request->validate(['service' => 'required|string']);
        return back()->with('output', MonitService::unmonitorService($request->input('service')));
    }
}
