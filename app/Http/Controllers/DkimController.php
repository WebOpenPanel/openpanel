<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class DkimController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $enabled = $this->isInstalled();
        $keys = $enabled ? $this->listKeys() : [];

        return view('dkim.index', compact('enabled', 'keys'));
    }

    public function generate(Request $request)
    {
        $request->validate(['domain' => 'required|string|max:255']);
        $domain = strtolower($request->domain);

        $result = $this->process()->run("opendkim-genkey -D /etc/opendkim/keys/{$domain} -d {$domain} -s default 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Key gen failed: ' . $result->errorOutput());
        }

        $this->process()->run("chown -R opendkim:opendkim /etc/opendkim/keys/{$domain}");

        $keyFile = "/etc/opendkim/keys/{$domain}/default.txt";
        $dnsRecord = file_exists($keyFile) ? file_get_contents($keyFile) : '';

        $signingTable = '/etc/opendkim/SigningTable';
        $keyTable = '/etc/opendkim/KeyTable';

        file_put_contents($signingTable, "*@{$domain} default._domainkey.{$domain}\n", FILE_APPEND);
        file_put_contents($keyTable, "default._domainkey.{$domain} {$domain}:default:/etc/opendkim/keys/{$domain}/default.private\n", FILE_APPEND);

        $this->process()->run("systemctl restart opendkim");

        return back()->with('success', "DKIM key generated for {$domain}.")->with('dnsRecord', $dnsRecord);
    }

    public function viewKey(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        $domain = strtolower($request->domain);
        $keyFile = "/etc/opendkim/keys/{$domain}/default.txt";
        $dns = file_exists($keyFile) ? file_get_contents($keyFile) : 'Key not found.';
        return view('dkim.key', compact('dns', 'domain'));
    }

    public function toggle()
    {
        if ($this->isInstalled()) {
            $action = $this->process()->run("systemctl is-active opendkim")->successful() ? 'stop' : 'start';
            $this->process()->run("systemctl {$action} opendkim");
            return back()->with('success', "OpenDKIM {$action}ed.");
        }
        $result = $this->process()->run("dnf -y install opendkim 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Install failed: ' . $result->errorOutput());
        }
        $this->process()->run("systemctl enable --now opendkim");
        return back()->with('success', 'OpenDKIM installed and started.');
    }

    protected function isInstalled(): bool
    {
        return $this->process()->run("which opendkim-genkey 2>/dev/null")->successful();
    }

    protected function listKeys(): array
    {
        $dir = '/etc/opendkim/keys';
        if (!is_dir($dir)) return [];
        return array_values(array_diff(scandir($dir), ['.', '..']));
    }
}
