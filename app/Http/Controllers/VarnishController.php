<?php

namespace App\Http\Controllers;

use App\Services\ServerService;
use App\Services\ShellService;
use App\Services\WebServerService;
use Illuminate\Http\Request;

class VarnishController extends Controller
{
    public function index()
    {
        $installed = file_exists('/usr/lib/systemd/system/varnish.service');
        $status = $installed ? $this->getServiceStatus() : ['running' => false];
        $config = $installed ? WebServerService::getVarnishConfig() : '';
        $vcl = $installed ? $this->getDefaultVcl() : '';
        return view('varnish.index', compact('installed', 'status', 'config', 'vcl'));
    }

    public function start()
    {
        $output = ServerService::manageServices('start', ['varnish']);
        return back()->with('success', 'Varnish started.')->with('output', $output);
    }

    public function stop()
    {
        $output = ServerService::manageServices('stop', ['varnish']);
        return back()->with('success', 'Varnish stopped.')->with('output', $output);
    }

    public function restart()
    {
        $output = ServerService::manageServices('restart', ['varnish']);
        return back()->with('success', 'Varnish restarted.')->with('output', $output);
    }

    public function saveConfig(Request $request)
    {
        $request->validate(['config' => 'required|string']);
        WebServerService::saveVarnishConfig($request->input('config'));
        return back()->with('success', 'Varnish configuration saved and service restarted.');
    }

    public function saveVcl(Request $request)
    {
        $request->validate(['vcl' => 'required|string']);
        $vclPath = WebServerService::VARNISH_DEFAULT_VCL;
        ShellService::writeFile($vclPath, $request->input('vcl'));
        ServerService::manageServices('restart', ['varnish']);
        return back()->with('success', 'VCL saved and Varnish restarted.');
    }

    public function clearCache()
    {
        $output = ShellService::exec('varnishadm ban req.url \".*\" 2>&1 || varnishncsa -D 2>&1');
        return back()->with('success', 'Varnish cache cleared.')->with('output', $output);
    }

    public function install()
    {
        $output = ShellService::exec('dnf -y install varnish 2>&1');
        if (file_exists('/usr/lib/systemd/system/varnish.service')) {
            ShellService::exec('systemctl enable varnish && systemctl start varnish');
            return back()->with('success', 'Varnish installed and started.')->with('output', $output);
        }
        return back()->with('error', 'Varnish installation failed.')->with('output', $output);
    }

    public function uninstall()
    {
        ServerService::manageServices('stop', ['varnish']);
        ShellService::exec('systemctl disable varnish 2>/dev/null; dnf -y remove varnish 2>&1');
        return back()->with('success', 'Varnish uninstalled.');
    }

    private function getServiceStatus(): array
    {
        $running = ShellService::exec('systemctl is-active varnish 2>/dev/null');
        $version = ShellService::exec('varnishd -V 2>&1 | head -1');
        return [
            'running' => trim($running) === 'active',
            'version' => trim($version),
        ];
    }

    private function getDefaultVcl(): string
    {
        $vclPath = WebServerService::VARNISH_DEFAULT_VCL;
        return file_exists($vclPath) ? ShellService::readFile($vclPath) : '';
    }
}
