<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class MxRoutingController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $domains = $this->listDomains();
        $current = [];

        foreach ($domains as $domain) {
            $result = $this->process()->run("dig +short MX {$domain} 2>/dev/null");
            $current[$domain] = trim($result->output()) ?: 'local';
        }

        return view('mx-routing.index', compact('domains', 'current'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'routing' => 'required|in:local,remote,backup',
            'mx_host' => 'nullable|string',
            'priority' => 'nullable|integer',
        ]);

        $domain = $request->domain;
        $routing = $request->routing;

        $relayFile = '/etc/postfix/relay_domains';
        $transportFile = '/etc/postfix/transport';

        if ($routing === 'remote') {
            $mx = $request->mx_host;
            $pri = $request->priority ?? 10;
            $this->process()->run("grep -q '{$domain}' {$relayFile} 2>/dev/null || echo '{$domain}' >> {$relayFile}");
            $this->process()->run("grep -q '{$domain}' {$transportFile} 2>/dev/null || echo '{$domain} smtp:[{$mx}]' >> {$transportFile}");
        } else {
            $this->process()->run("sed -i '/{$domain}/d' {$relayFile} 2>/dev/null");
            $this->process()->run("sed -i '/{$domain}/d' {$transportFile} 2>/dev/null");
        }

        $this->process()->run("postmap {$transportFile} 2>/dev/null");
        $this->process()->run("systemctl reload postfix");

        return back()->with('success', "MX routing for '{$domain}' set to '{$routing}'.");
    }

    protected function listDomains(): array
    {
        $result = $this->process()->run("awk '{print $1}' /etc/postfix/vhost 2>/dev/null | sort -u");
        return array_filter(explode("\n", trim($result->output())));
    }
}
