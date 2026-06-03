<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Process\Factory as ProcessFactory;

class FreednsController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $zones = $this->getFreeDnsZones();
        return view('freedns.index', compact('zones'));
    }

    public function addZone(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'ip' => 'required|ip',
        ]);

        $domain = escapeshellarg($request->domain);
        $ip = escapeshellarg($request->ip);

        $result = $this->process()->run("rndc addzone {$domain} in 'free' { file \"free/{$request->domain}.db\"; } 2>&1");

        if ($result->successful()) {
            $zoneContent = $this->generateZoneContent($request->domain, $request->ip);
            $zoneDir = '/var/named/free';
            if (!is_dir($zoneDir)) {
                mkdir($zoneDir, 0755, true);
            }
            file_put_contents("{$zoneDir}/{$request->domain}.db", $zoneContent);
            $this->process()->run("systemctl reload named 2>&1");
            return back()->with('success', "FreeDNS zone added for {$request->domain}");
        }

        return back()->with('error', 'Failed to add zone: ' . $result->errorOutput());
    }

    public function deleteZone(Request $request)
    {
        $request->validate(['domain' => 'required|string']);

        $domain = escapeshellarg($request->domain);
        $result = $this->process()->run("rndc delzone {$domain} 2>&1");

        if ($result->successful()) {
            @unlink("/var/named/free/{$request->domain}.db");
            $this->process()->run("systemctl reload named 2>&1");
            return back()->with('success', "FreeDNS zone removed for {$request->domain}");
        }

        return back()->with('error', 'Failed to remove zone: ' . $result->errorOutput());
    }

    public function editZone(Request $request)
    {
        $request->validate(['domain' => 'required|string']);

        $zoneFile = "/var/named/free/{$request->domain}.db";
        if (!file_exists($zoneFile)) {
            return back()->with('error', 'Zone file not found.');
        }

        $content = file_get_contents($zoneFile);
        return view('freedns.edit', ['domain' => $request->domain, 'content' => $content]);
    }

    public function saveZone(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'content' => 'required|string',
        ]);

        $zoneFile = "/var/named/free/{$request->domain}.db";
        file_put_contents($zoneFile, $request->content);

        $domain = escapeshellarg($request->domain);
        $this->process()->run("named-checkzone {$domain} {$zoneFile} 2>&1");
        $this->process()->run("systemctl reload named 2>&1");

        return back()->with('success', 'Zone file saved and reloaded.');
    }

    protected function getFreeDnsZones(): array
    {
        $output = $this->process()->run("rndc zonestatus 2>/dev/null | grep -i free")->output();
        $zones = [];
        if (file_exists('/var/named/free')) {
            foreach (glob('/var/named/free/*.db') as $file) {
                $zones[] = basename($file, '.db');
            }
        }
        return $zones;
    }

    protected function generateZoneContent(string $domain, string $ip): string
    {
        $serial = date('Ymd') . '01';
        return "\$TTL 3600
@   IN  SOA ns1.{$domain}. admin.{$domain}. (
        {$serial}  ; Serial
        3600       ; Refresh
        1800       ; Retry
        604800     ; Expire
        86400      ; Minimum TTL
)
@       IN  NS      ns1.{$domain}.
@       IN  NS      ns2.{$domain}.
@       IN  A       {$ip}
ns1     IN  A       {$ip}
ns2     IN  A       {$ip}
www     IN  A       {$ip}
mail    IN  A       {$ip}
@       IN  MX  10  mail.{$domain}.
@       IN  TXT     \"v=spf1 +a +mx +ip:{$ip} ~all\"
";
    }
}
