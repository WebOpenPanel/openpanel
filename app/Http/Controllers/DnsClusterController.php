<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class DnsClusterController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $slaves = $this->listSlaves();
        $zones = $this->listZones();

        return view('dns-cluster.index', compact('slaves', 'zones'));
    }

    public function addSlave(Request $request)
    {
        $request->validate([
            'host' => 'required|string|max:255',
            'key' => 'required|string',
        ]);

        $config = '/etc/openpanel/dns-cluster.conf';
        $slaves = file_exists($config) ? json_decode(file_get_contents($config), true) ?? [] : [];
        $slaves[] = ['host' => $request->host, 'key' => $request->key, 'added' => now()->toDateTimeString()];
        file_put_contents($config, json_encode($slaves, JSON_PRETTY_PRINT));

        return back()->with('success', "Slave DNS '{$request->host}' added.");
    }

    public function removeSlave(Request $request)
    {
        $request->validate(['host' => 'required|string']);
        $config = '/etc/openpanel/dns-cluster.conf';
        $slaves = file_exists($config) ? json_decode(file_get_contents($config), true) ?? [] : [];
        $slaves = array_filter($slaves, fn($s) => $s['host'] !== $request->host);
        file_put_contents($config, json_encode(array_values($slaves), JSON_PRETTY_PRINT));
        return back()->with('success', "Slave '{$request->host}' removed.");
    }

    public function sync(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        $domain = $request->domain;

        $config = '/etc/openpanel/dns-cluster.conf';
        $slaves = file_exists($config) ? json_decode(file_get_contents($config), true) ?? [] : [];
        $results = [];

        foreach ($slaves as $slave) {
            $result = $this->process()->run("rndc -s {$slave['host']} -y '{$slave['key']}' retransfer {$domain} 2>&1");
            $results[$slave['host']] = $result->successful() ? 'OK' : $result->errorOutput();
        }

        return back()->with('success', 'Sync complete.')->with('results', $results);
    }

    protected function listSlaves(): array
    {
        $config = '/etc/openpanel/dns-cluster.conf';
        return file_exists($config) ? json_decode(file_get_contents($config), true) ?? [] : [];
    }

    protected function listZones(): array
    {
        $result = $this->process()->run("ls /var/named/*.db 2>/dev/null | xargs -I{} basename {} .db");
        return array_filter(explode("\n", trim($result->output())));
    }
}
