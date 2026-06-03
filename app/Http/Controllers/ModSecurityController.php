<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class ModSecurityController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $installed = $this->isInstalled();
        $enabled = $installed ? $this->isEnabled() : false;
        $rulesets = $installed ? $this->listRulesets() : [];
        $logs = $installed ? $this->recentLogs() : [];

        return view('modsecurity.index', compact('installed', 'enabled', 'rulesets', 'logs'));
    }

    public function toggle(Request $request)
    {
        $action = $request->validate(['enabled' => 'required|boolean'])['enabled'];
        $state = $action ? 'On' : 'Off';

        $conf = '/etc/nginx/modsec/modsecurity.conf';
        if (file_exists($conf)) {
            $content = file_get_contents($conf);
            $content = preg_replace('/SecRuleEngine\s+\w+/', "SecRuleEngine {$state}", $content);
            file_put_contents($conf, $content);
            $this->process()->run("systemctl reload nginx");
            return back()->with('success', "ModSecurity {$state}.");
        }
        return back()->with('error', 'ModSecurity config not found.');
    }

    public function install()
    {
        $result = $this->process()->run("dnf -y install mod_security mod_security-mlogc 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Install failed: ' . $result->errorOutput());
        }
        return back()->with('success', 'ModSecurity installed. Configure rulesets manually.');
    }

    public function updateRules()
    {
        $result = $this->process()->run("cd /etc/nginx/modsec && git pull 2>&1");
        if ($result->failed()) {
            $result = $this->process()->run("cd /etc/nginx/modsec && git clone https://github.com/coreruleset/coreruleset.git . 2>&1");
        }
        $this->process()->run("systemctl reload nginx");
        return back()->with('success', 'Rules updated.');
    }

    public function viewLog()
    {
        $log = file_get_contents('/var/log/modsec_audit.log') ?: 'No log entries.';
        return view('modsecurity.log', compact('log'));
    }

    protected function isInstalled(): bool
    {
        return file_exists('/etc/nginx/modsec') || $this->process()->run("rpm -q mod_security")->successful();
    }

    protected function isEnabled(): bool
    {
        $conf = '/etc/nginx/modsec/modsecurity.conf';
        if (!file_exists($conf)) return false;
        $content = file_get_contents($conf);
        return preg_match('/SecRuleEngine\s+On/i', $content);
    }

    protected function listRulesets(): array
    {
        $dir = '/etc/nginx/modsec/rules';
        if (!is_dir($dir)) return [];
        return array_values(array_diff(scandir($dir), ['.', '..']));
    }

    protected function recentLogs(): array
    {
        $log = '/var/log/modsec_audit.log';
        if (!file_exists($log)) return [];
        $lines = [];
        $fh = fopen($log, 'r');
        if ($fh) {
            fseek($fh, -8192, SEEK_END);
            $lines = array_slice(explode("\n", fread($fh, 8192)), -50);
            fclose($fh);
        }
        return array_filter($lines);
    }
}
