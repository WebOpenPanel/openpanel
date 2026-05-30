<?php

namespace App\Http\Controllers;

use App\Services\ServerService;
use App\Services\WebServerService;
use App\Services\PhpService;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function phpInfo()
    {
        $phpinfo = PhpService::getPhpInfo();
        return view('server.phpinfo', compact('phpinfo'));
    }

    public function processList()
    {
        $processes = ServerService::getProcessList();
        return view('server.processes', compact('processes'));
    }

    public function killProcess(Request $request)
    {
        $request->validate(['pid' => 'required|integer', 'signal' => 'nullable|string']);
        ServerService::killProcess($request->pid, $request->signal ?? '9');
        return back()->with('success', "Process {$request->pid} killed.");
    }

    public function networkConfig()
    {
        $interfaces = ServerService::getNetworkInterfaces();
        $bandwidth = ServerService::getBandwidth();
        $netstat = ServerService::getNetstat();
        return view('server.network', compact('interfaces', 'bandwidth', 'netstat'));
    }

    public function diskUsage()
    {
        $disks = ServerService::getDiskUsage();
        return view('server.disk', compact('disks'));
    }

    public function serviceList()
    {
        $services = ServerService::getServiceList();
        $startup = ServerService::getStartupServices();
        return view('server.services', compact('services', 'startup'));
    }

    public function serviceAction(string $action, string $service)
    {
        $output = ServerService::serviceAction($action, $service);
        return back()->with('output', $output)->with('success', "Service {$service}: {$action}");
    }

    public function hostname()
    {
        $hostname = ServerService::getHostname();
        $loadAvg = ServerService::getLoadAvg();
        $uptime = ServerService::getUptime();
        return view('server.hostname', compact('hostname', 'loadAvg', 'uptime'));
    }

    public function setHostname(Request $request)
    {
        $request->validate(['hostname' => 'required|string|max:255']);
        ServerService::setHostname($request->hostname);
        return back()->with('success', "Hostname set to {$request->hostname}");
    }

    public function dateTime()
    {
        $time = ServerService::getServerTime();
        $timezones = ServerService::getTimezones();
        return view('server.time', compact('time', 'timezones'));
    }

    public function setTimezone(Request $request)
    {
        $request->validate(['timezone' => 'required|string']);
        ServerService::setTimezone($request->timezone);
        return back()->with('success', 'Timezone updated.');
    }

    public function reboot()
    {
        ServerService::reboot();
        return back()->with('success', 'Server rebooting...');
    }

    public function shutdown()
    {
        ServerService::shutdown();
        return back()->with('success', 'Server shutting down...');
    }

    public function sshKeys()
    {
        $keys = ServerService::getSshKeys();
        return view('server.ssh-keys', compact('keys'));
    }

    public function generateSshKey(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string|in:rsa,ed25519,ecdsa',
            'bits' => 'nullable|integer',
        ]);
        $result = ServerService::generateSshKey($request->type ?? 'rsa', $request->bits ?? 4096);
        return back()->with('success', 'SSH key generated.')->with('key', $result);
    }

    public function changeRootPassword(Request $request)
    {
        $request->validate(['password' => 'required|string|min:8|confirmed']);
        ServerService::changeRootPassword($request->password);
        return back()->with('success', 'Root password changed.');
    }

    public function yumPackages(Request $request)
    {
        $packages = ServerService::getYumPackages($request->search ?? '');
        return view('server.yum', compact('packages'));
    }

    public function yumInstall(Request $request)
    {
        $request->validate(['package' => 'required|string']);
        $output = ServerService::yumInstall($request->package);
        return back()->with('output', $output)->with('success', "Package {$request->package} installed.");
    }

    public function yumUpdate(Request $request)
    {
        $output = ServerService::yumUpdate($request->package ?? '');
        return back()->with('output', $output)->with('success', 'System updated.');
    }

    public function webserver()
    {
        $active = WebServerService::getActiveWebServer();
        $conf = WebServerService::getMainConf($active);
        $templates = WebServerService::getVhostTemplates($active);
        return view('server.webserver', compact('active', 'conf', 'templates'));
    }

    public function setWebserver(Request $request)
    {
        $request->validate(['server' => 'required|in:apache,nginx,nginx_apache,litespeed']);
        WebServerService::setWebServer($request->input('server'));
        return back()->with('success', 'Web server set to ' . $request->input('server'));
    }

    public function phpManager()
    {
        $versions = PhpService::getInstalledVersions();
        $default = PhpService::getDefaultVersion();
        return view('server.php', compact('versions', 'default'));
    }

    public function setPhpDefault(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $output = PhpService::setDefaultCli($request->version);
        return back()->with('output', $output)->with('success', 'Default PHP updated.');
    }

    public function terminal()
    {
        return view('server.terminal');
    }

    public function runCommand(Request $request)
    {
        $request->validate(['command' => 'required|string|max:1000']);
        $output = ServerService::runShellCommand($request->command);
        return back()->with('output', $output);
    }
}
