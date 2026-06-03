<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class LoginSecurityController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $failedLogins = $this->getFailedLogins();
        $activeUsers = $this->getActiveUsers();
        $lastLogins = $this->getLastLogins();
        $sshConfig = $this->getSshConfig();

        return view('login-security.index', compact('failedLogins', 'activeUsers', 'lastLogins', 'sshConfig'));
    }

    public function blockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $result = $this->process()->run("iptables -A INPUT -s {$request->ip} -j DROP 2>&1");
        $this->process()->run("iptables-save > /etc/sysconfig/iptables 2>/dev/null");
        return back()->with($result->successful() ? 'success' : 'error', $result->successful() ? "IP {$request->ip} blocked." : $result->errorOutput());
    }

    public function unblockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $result = $this->process()->run("iptables -D INPUT -s {$request->ip} -j DROP 2>&1");
        $this->process()->run("iptables-save > /etc/sysconfig/iptables 2>/dev/null");
        return back()->with($result->successful() ? 'success' : 'error', $result->successful() ? "IP {$request->ip} unblocked." : $result->errorOutput());
    }

    public function kickUser(Request $request)
    {
        $request->validate(['tty' => 'required|string']);
        $tty = preg_replace('/[^a-zA-Z0-9\/]/', '', $request->tty);
        $this->process()->run("pkill -t {$tty}");
        return back()->with('success', "Session {$tty} terminated.");
    }

    public function updateSsh(Request $request)
    {
        $request->validate(['config' => 'required|string']);
        file_put_contents('/etc/ssh/sshd_config', $request->config);
        $this->process()->run("systemctl restart sshd");
        return back()->with('success', 'SSH config updated.');
    }

    protected function getFailedLogins(): array
    {
        $result = $this->process()->run("journalctl _SYSTEMD_UNIT=sshd.service --no-pager -n 100 2>/dev/null | grep -i 'failed\\|invalid' | tail -20");
        return array_filter(explode("\n", trim($result->output())));
    }

    protected function getActiveUsers(): array
    {
        $result = $this->process()->run("w -h 2>/dev/null");
        $users = [];
        foreach (array_filter(explode("\n", trim($result->output()))) as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 3) {
                $users[] = ['user' => $parts[0], 'tty' => $parts[1], 'from' => $parts[2] ?? '-'];
            }
        }
        return $users;
    }

    protected function getLastLogins(): array
    {
        $result = $this->process()->run("last -n 20 2>/dev/null");
        return array_filter(explode("\n", trim($result->output())));
    }

    protected function getSshConfig(): string
    {
        return file_get_contents('/etc/ssh/sshd_config') ?: '';
    }
}
