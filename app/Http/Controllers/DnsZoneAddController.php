<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class DnsZoneAddController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $serverIp = trim((string) $this->process()->run("hostname -I | awk '{print \$1}'")->output());
        return view('dns-zone-add.index', compact('serverIp'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'ip' => 'required|ip',
            'email' => 'nullable|email',
        ]);

        $domain = $request->domain;
        $ip = $request->ip;
        $email = str_replace('@', '.', ($request->email ?? "admin@{$domain}"));
        $serial = date('Ymd') . '01';
        $zoneDir = config('openpanel.paths.dns_zone_dir', '/var/named');

        $zoneContent = <<<ZONE
\$TTL 3600
@   IN  SOA ns1.{$domain}. {$email}. (
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
ftp     IN  A       {$ip}
@       IN  MX  10  mail.{$domain}.
@       IN  TXT     "v=spf1 +a +mx +ip:{$ip} ~all"
ZONE;

        $zoneFile = "{$zoneDir}/{$domain}.db";
        file_put_contents($zoneFile, $zoneContent);

        $domainArg = escapeshellarg($domain);
        $zoneFileArg = escapeshellarg($zoneFile);
        $this->process()->run("named-checkzone {$domainArg} {$zoneFileArg} 2>&1");

        $namedConf = config('openpanel.paths.named_conf', '/etc/named.conf');
        $zoneEntry = "\nzone \"{$domain}\" IN {\n    type master;\n    file \"{$zoneFile}\";\n    allow-update { none; };\n};\n";

        if (file_exists($namedConf)) {
            $currentConf = file_get_contents($namedConf);
            if (!str_contains($currentConf, "zone \"{$domain}\"")) {
                file_put_contents($namedConf, $currentConf . $zoneEntry, FILE_APPEND);
            }
        }

        $this->process()->run("systemctl reload named 2>&1");

        return back()->with('success', "DNS zone created for {$domain}");
    }
}
