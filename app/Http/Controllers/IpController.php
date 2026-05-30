<?php

namespace App\Http\Controllers;

use App\Services\IpService;
use Illuminate\Http\Request;

class IpController extends Controller
{
    public function index()
    {
        $ips = IpService::getIpList();
        return view('ip.index', compact('ips'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'netmask' => 'required|string',
            'interface' => 'required|string',
        ]);
        IpService::addIp($request->ip, $request->netmask, $request->interface, $request->owner ?? 'root');
        return back()->with('success', "IP '{$request->ip}' added successfully.");
    }

    public function destroy(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        IpService::deleteIp($request->ip, $request->interface ?? 'eth0');
        return back()->with('success', 'IP deleted successfully.');
    }

    public function details(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $details = IpService::getIpDetails($request->ip);
        return view('ip.details', compact('details'));
    }

    public function setShared(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        IpService::setAsShared($request->ip);
        return back()->with('success', 'IP set as shared.');
    }

    public function setDedicated(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        IpService::setAsDedicated($request->ip);
        return back()->with('success', 'IP set as dedicated.');
    }

    public function nat()
    {
        $config = IpService::getNatConfig();
        $nat = IpService::networkingNat();
        return view('ip.nat', compact('config', 'nat'));
    }

    public function saveNat(Request $request)
    {
        $request->validate([
            'nat' => 'required|in:ON,OFF',
            'local_ip' => 'required|ip',
            'public_ip' => 'required|ip',
        ]);
        IpService::setNatConfig([
            'nat' => $request->nat,
            'local_ip' => $request->local_ip,
            'public_ip' => $request->public_ip,
        ]);
        return back()->with('success', 'NAT configuration saved.');
    }

    public function dnsResolvers()
    {
        $resolvers = IpService::getDnsResolvers();
        return view('ip.dns-resolvers', compact('resolvers'));
    }

    public function saveDnsResolvers(Request $request)
    {
        $request->validate(['servers' => 'required|array']);
        IpService::setDnsResolvers($request->servers);
        return back()->with('success', 'DNS resolvers saved.');
    }
}
