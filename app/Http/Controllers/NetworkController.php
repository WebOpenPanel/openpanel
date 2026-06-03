<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class NetworkController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $interfaces = $this->listInterfaces();
        $dns = $this->getDnsConfig();
        $hostname = trim($this->process()->run('hostname -f')->output());
        $ip = trim($this->process()->run("hostname -I | awk '{print \$1}'")->output());

        return view('network.index', compact('interfaces', 'dns', 'hostname', 'ip'));
    }

    public function updateHostname(Request $request)
    {
        $request->validate(['hostname' => 'required|string|max:253']);
        $this->process()->run("hostnamectl set-hostname {$request->hostname}");
        return back()->with('success', "Hostname set to {$request->hostname}.");
    }

    public function updateDns(Request $request)
    {
        $request->validate(['dns' => 'required|string']);
        file_put_contents('/etc/resolv.conf', $request->dns);
        return back()->with('success', 'DNS config updated.');
    }

    public function addIp(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'netmask' => 'required|string',
            'interface' => 'required|string',
        ]);

        $iface = $request->interface;
        $ip = $request->ip;
        $mask = $request->netmask;

        $config = <<<IFACE
DEVICE={$iface}:1
IPADDR={$ip}
NETMASK={$mask}
ONBOOT=yes
IFACE;

        $file = "/etc/sysconfig/network-scripts/ifcfg-{$iface}:1";
        file_put_contents($file, $config);
        $this->process()->run("ifup {$iface}:1 2>&1");

        return back()->with('success', "IP {$ip} added to {$iface}.");
    }

    public function removeIp(Request $request)
    {
        $request->validate(['interface' => 'required|string']);
        $iface = preg_replace('/[^a-zA-Z0-9:]/', '', $request->interface);
        $this->process()->run("ifdown {$iface} 2>&1");
        @unlink("/etc/sysconfig/network-scripts/ifcfg-{$iface}");
        return back()->with('success', "Interface {$iface} removed.");
    }

    protected function listInterfaces(): array
    {
        $result = $this->process()->run("ip -o addr show | awk '{print $2, $4}'");
        $interfaces = [];
        foreach (array_filter(explode("\n", trim($result->output()))) as $line) {
            $parts = explode(' ', $line);
            if (count($parts) >= 2 && $parts[0] !== 'lo') {
                $interfaces[] = ['name' => $parts[0], 'address' => $parts[1]];
            }
        }
        return $interfaces;
    }

    protected function getDnsConfig(): string
    {
        return file_get_contents('/etc/resolv.conf') ?: '';
    }
}
