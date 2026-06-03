<?php

namespace App\Http\Controllers;

use App\Services\FirewallService;
use Illuminate\Http\Request;

class FirewallController extends Controller
{
    public function __construct(protected FirewallService $fw) {}

    public function index()
    {
        $status = $this->fw->getStatus();
        $installed = $this->fw->isInstalled();
        $active = $installed ? $this->fw->isActive() : false;
        $ports = $installed ? $this->fw->listOpenPorts() : [];
        $blocked = $installed ? $this->fw->listBlockedIps() : [];
        $allowed = $installed ? $this->fw->listAllowedIps() : [];
        $rules = $installed ? $this->fw->getRawRules() : '';

        return view('firewall.index', compact('status', 'installed', 'active', 'ports', 'blocked', 'allowed', 'rules'));
    }

    public function install()
    {
        $ok = $this->fw->install();
        return back()->with($ok ? 'success' : 'error', $ok ? "Firewall installed ({$this->fw->backend()})" : 'Firewall installation failed');
    }

    public function toggle(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:start,stop,restart'])['action'];
        $ok = $this->fw->$action();
        return back()->with($ok ? 'success' : 'error', "Firewall {$action}: " . ($ok ? 'OK' : 'FAILED'));
    }

    public function blockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ok = $this->fw->blockIp($request->ip);
        return back()->with($ok ? 'success' : 'error', $ok ? "Blocked {$request->ip}" : 'Block failed');
    }

    public function unblockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ok = $this->fw->unblockIp($request->ip);
        return back()->with($ok ? 'success' : 'error', $ok ? "Unblocked {$request->ip}" : 'Unblock failed');
    }

    public function allowIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ok = $this->fw->allowIp($request->ip);
        return back()->with($ok ? 'success' : 'error', $ok ? "Allowed {$request->ip}" : 'Allow failed');
    }

    public function removeAllowIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ok = $this->fw->removeAllowIp($request->ip);
        return back()->with($ok ? 'success' : 'error', $ok ? "Removed {$request->ip}" : 'Remove failed');
    }

    public function allowPort(Request $request)
    {
        $request->validate(['port' => 'required|string|max:20']);
        $ok = $this->fw->allowPort($request->port);
        return back()->with($ok ? 'success' : 'error', $ok ? "Port {$request->port} opened" : 'Failed');
    }

    public function denyPort(Request $request)
    {
        $request->validate(['port' => 'required|string|max:20']);
        $ok = $this->fw->denyPort($request->port);
        return back()->with($ok ? 'success' : 'error', $ok ? "Port {$request->port} closed" : 'Failed');
    }
}
