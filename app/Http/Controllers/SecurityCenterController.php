<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class SecurityCenterController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $sshdConfig = file_exists('/etc/ssh/sshd_config') ? file_get_contents('/etc/ssh/sshd_config') : '';

        $checks = [
            'ssh_root_login' => (bool) preg_match('/^PermitRootLogin\s+yes/m', $sshdConfig),
            'ssh_password_auth' => (bool) preg_match('/^PasswordAuthentication\s+yes/m', $sshdConfig),
            'selinux' => trim((string) $this->process()->run("getenforce 2>/dev/null")->output()),
            'firewall' => $this->process()->run("which csf 2>/dev/null")->successful(),
            'firewall_active' => $this->process()->run("csf -l 2>/dev/null | head -1")->successful(),
            'fail2ban' => trim((string) $this->process()->run("systemctl is-active fail2ban 2>/dev/null")->output()) === 'active',
            'open_ports' => trim((string) $this->process()->run("ss -tuln | grep LISTEN | wc -l")->output()),
            'last_logins' => $this->process()->run("last -n 10 2>/dev/null")->output(),
            'failed_logins' => trim((string) $this->process()->run("journalctl _SYSTEMD_UNIT=sshd.service --no-pager -n 20 2>/dev/null | grep -i 'failed\|invalid' | wc -l")->output()),
            'kernel_version' => trim((string) $this->process()->run("uname -r")->output()),
            'uptime' => trim((string) $this->process()->run("uptime -p")->output()),
        ];

        return view('security-center.index', compact('checks'));
    }

    public function harden(Request $request)
    {
        $request->validate([
            'action' => 'required|in:disable-root-ssh,disable-password-auth,enable-fail2ban,update-packages',
        ]);

        $result = match ($request->action) {
            'disable-root-ssh' => $this->process()->run("sed -i 's/^PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config && systemctl restart sshd 2>&1"),
            'disable-password-auth' => $this->process()->run("sed -i 's/^PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config && systemctl restart sshd 2>&1"),
            'enable-fail2ban' => $this->process()->run("dnf -y install fail2ban && systemctl enable --now fail2ban 2>&1"),
            'update-packages' => $this->process()->run("dnf -y update --security 2>&1"),
        };

        return back()->with(
            $result->successful() ? 'success' : 'error',
            $result->output() ?: $result->errorOutput()
        );
    }
}
