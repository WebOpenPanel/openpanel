<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class SpfController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $serverIp = trim($this->process()->run("hostname -I | awk '{print \$1}'")->output());
        $hostname = trim($this->process()->run('hostname -f')->output());
        $spfRecord = "v=spf1 +a +mx +ip4:{$serverIp} ~all";
        $dmarcRecord = "v=DMARC1; p=none; rua=mailto:admin@{$hostname}";

        return view('spf.index', compact('serverIp', 'hostname', 'spfRecord', 'dmarcRecord'));
    }

    public function check(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        $domain = $request->domain;

        $domainArg = escapeshellarg($domain);
        $dmarcArg = escapeshellarg("_dmarc.{$domain}");
        $spf = $this->process()->run("dig +short TXT {$domainArg} 2>/dev/null | grep spf");
        $dmarc = $this->process()->run("dig +short TXT {$dmarcArg} 2>/dev/null");

        return view('spf.check', [
            'domain' => $domain,
            'spf' => trim($spf->output()),
            'dmarc' => trim($dmarc->output()),
        ]);
    }
}
