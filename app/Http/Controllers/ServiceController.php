<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\ServerService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('display_name')->get();
        return view('services.index', compact('services'));
    }

    public function restart(Service $service)
    {
        ServerService::serviceAction('restart', $service->service_name);
        $service->update([
            'last_restarted_at' => now(),
            'restart_count' => $service->restart_count + 1,
        ]);
        return back()->with('success', "Service '{$service->display_name}' restarted.");
    }

    public function toggle(Service $service)
    {
        $action = $service->status === 'running' ? 'stop' : 'start';
        ServerService::serviceAction($action, $service->service_name);
        $newStatus = $action === 'start' ? 'running' : 'stopped';
        $service->update(['status' => $newStatus]);
        return back()->with('success', "Service '{$service->display_name}' is now {$newStatus}.");
    }

    public function toggleBoot(Service $service)
    {
        $action = $service->enabled_on_boot ? 'disable' : 'enable';
        ServerService::chkConfigAction($action, $service->service_name);
        $service->update(['enabled_on_boot' => !$service->enabled_on_boot]);
        $status = $service->enabled_on_boot ? 'enabled' : 'disabled';
        return back()->with('success', "Boot startup {$status} for '{$service->display_name}'.");
    }

    public function toggleMonitor(Service $service)
    {
        $service->update(['monitor_enabled' => !$service->monitor_enabled]);
        $status = $service->monitor_enabled ? 'enabled' : 'disabled';
        return back()->with('success', "Monitoring {$status} for '{$service->display_name}'.");
    }

    public function action(Request $request, Service $service)
    {
        $request->validate(['action' => 'required|in:start,stop,restart,reload,status']);
        $output = ServerService::serviceAction($request->action, $service->service_name);
        return back()->with('output', $output);
    }

    public function config(Service $service)
    {
        $configPaths = [
            'nginx' => '/etc/nginx/nginx.conf',
            'httpd' => '/etc/httpd/conf/httpd.conf',
            'apache' => '/etc/httpd/conf/httpd.conf',
            'mysqld' => '/etc/my.cnf',
            'mariadb' => '/etc/my.cnf',
            'postfix' => '/etc/postfix/main.cf',
            'dovecot' => '/etc/dovecot/dovecot.conf',
            'named' => '/etc/named.conf',
            'php-fpm' => '/etc/php-fpm.conf',
            'redis' => '/etc/redis.conf',
            'pure-ftpd' => '/etc/pure-ftpd/pure-ftpd.conf',
        ];
        $configFile = $configPaths[$service->service_name] ?? '';
        $content = $configFile ? file_get_contents($configFile) : '';
        return view('services.config', compact('service', 'content', 'configFile'));
    }

    public function saveConfig(Request $request, Service $service)
    {
        $request->validate(['content' => 'required|string', 'path' => 'required|string']);
        file_put_contents($request->path, $request->content);
        return back()->with('success', 'Configuration saved.');
    }
}
