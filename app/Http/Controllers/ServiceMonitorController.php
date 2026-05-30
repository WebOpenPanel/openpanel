<?php

namespace App\Http\Controllers;

use App\Services\ServiceMonitorService;
use Illuminate\Http\Request;

class ServiceMonitorController extends Controller
{
    public function index()
    {
        $services = ServiceMonitorService::listServices();
        $enabled = ServiceMonitorService::isEnabled();
        $monitored = ServiceMonitorService::getMonitored();
        return view('service_monitor.index', compact('services', 'enabled', 'monitored'));
    }

    public function save(Request $request)
    {
        ServiceMonitorService::saveMonitored($request->input('services', []));
        if ($request->boolean('enabled')) {
            ServiceMonitorService::enable($request->input('email', 'root@localhost'), (int) $request->input('frequency', 5));
        } else {
            ServiceMonitorService::disable();
        }
        return back()->with('success', 'Service monitor config saved.');
    }
}
