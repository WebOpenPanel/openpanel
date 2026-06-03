<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class NameserverController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $nameservers = $this->getNameservers();
        $serverIp = trim($this->process()->run("hostname -I | awk '{print \$1}'")->output());
        $hostname = trim($this->process()->run('hostname -f')->output());

        return view('nameservers.index', compact('nameservers', 'serverIp', 'hostname'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'ns1' => 'required|string|max:255',
            'ns2' => 'required|string|max:255',
            'ns1_ip' => 'nullable|ip',
            'ns2_ip' => 'nullable|ip',
        ]);

        $config = <<<NAMED
NS1={$request->ns1}
NS2={$request->ns2}
NS1_IP={$request->ns1_ip}
NS2_IP={$request->ns2_ip}
NAMED;

        file_put_contents('/etc/openpanel/nameservers.conf', $config);

        return back()->with('success', 'Nameservers updated. Remember to register them at your registrar.');
    }

    protected function getNameservers(): array
    {
        $config = '/etc/openpanel/nameservers.conf';
        if (!file_exists($config)) {
            $ip = trim($this->process()->run("hostname -I | awk '{print \$1}'")->output());
            $host = trim($this->process()->run('hostname -f')->output());
            return [
                'ns1' => "ns1.{$host}", 'ns2' => "ns2.{$host}",
                'ns1_ip' => $ip, 'ns2_ip' => $ip,
            ];
        }
        $data = [];
        foreach (file($config, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^(\w+)=(.+)/', $line, $m)) {
                $data[strtolower($m[1])] = $m[2];
            }
        }
        return $data;
    }
}
