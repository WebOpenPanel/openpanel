<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class SlaveDnsController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $slaves = $this->listSlaves();
        return view('slave-dns.index', compact('slaves'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'zone' => 'required|string',
        ]);

        $conf = '/etc/named.conf';
        $block = <<<NAMED
zone "{$request->zone}" {
    type slave;
    file "slaves/{$request->zone}.db";
    masters { {$request->ip}; };
};

NAMED;

        file_put_contents($conf, $block, FILE_APPEND);
        $this->process()->run("systemctl reload named");
        return back()->with('success', "Slave zone '{$request->zone}' added.");
    }

    public function remove(Request $request)
    {
        $request->validate(['zone' => 'required|string']);
        $zone = $request->zone;
        $conf = '/etc/named.conf';
        $content = file_get_contents($conf);
        $content = preg_replace("/zone \"{$zone}\" \{[^}]+\}\;\n/s", '', $content);
        file_put_contents($conf, $content);
        @unlink("/var/named/slaves/{$zone}.db");
        $this->process()->run("systemctl reload named");
        return back()->with('success', "Slave zone '{$zone}' removed.");
    }

    protected function listSlaves(): array
    {
        $result = $this->process()->run("grep -A3 'type slave' /etc/named.conf 2>/dev/null | grep zone");
        $slaves = [];
        foreach (array_filter(explode("\n", trim($result->output()))) as $line) {
            if (preg_match('/zone "([^"]+)"/', $line, $m)) {
                $slaves[] = $m[1];
            }
        }
        return $slaves;
    }
}
