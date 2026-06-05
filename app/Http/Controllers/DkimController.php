<?php

namespace App\Http\Controllers;

use App\Services\EmailDeliverabilityService;
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
        $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
        ]);

        try {
            $result = (new EmailDeliverabilityService())->enableDkim($request->domain, 'default', false);
            return back()
                ->with('success', "DKIM key generated for {$result['domain']}.")
                ->with('dnsRecord', $result['records']['dkim']['value'] ?? '');
        } catch (\Throwable $e) {
            return back()->with('error', 'DKIM setup failed: ' . $e->getMessage());
        }
    }

    public function viewKey(Request $request)
    {
        $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
        ]);

        $domain = strtolower($request->domain);
        $record = (new EmailDeliverabilityService())->dnsHelperRecords($domain)['dkim']['value'] ?? null;
        $dns = $record ?: 'Key not found.';
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
        return array_values(array_filter(array_diff(scandir($dir), ['.', '..']), function ($domain) {
            return preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain);
        }));
    }
}
