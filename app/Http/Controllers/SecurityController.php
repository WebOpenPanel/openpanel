<?php

namespace App\Http\Controllers;

use App\Models\FirewallRule;
use App\Models\BlockedIp;
use App\Models\AllowedIp;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecurityController extends Controller
{
    public function firewall()
    {
        $rules = FirewallRule::orderBy('priority')->latest()->paginate(20);
        return view('security.firewall', compact('rules'));
    }

    public function addFirewallRule(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'action' => 'required|in:allow,deny,reject,drop',
            'protocol' => 'required|in:tcp,udp,icmp,all',
            'source_ip' => 'nullable|string|max:45',
            'source_port' => 'nullable|string|max:20',
            'destination_ip' => 'nullable|string|max:45',
            'destination_port' => 'nullable|string|max:20',
            'direction' => 'nullable|in:in,out,both',
            'comment' => 'nullable|string|max:255',
        ]);

        FirewallRule::create($request->all());
        return back()->with('success', 'Firewall rule added successfully.');
    }

    public function deleteFirewallRule(FirewallRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'Firewall rule deleted successfully.');
    }

    public function toggleFirewallRule(FirewallRule $rule)
    {
        $rule->update(['enabled' => !$rule->enabled]);
        return back()->with('success', 'Firewall rule toggled successfully.');
    }

    public function blockedIps()
    {
        $blockedIps = BlockedIp::latest()->paginate(20);
        return view('security.blocked-ips', compact('blockedIps'));
    }

    public function blockIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
        ]);

        BlockedIp::create([
            ...$request->all(),
            'added_by' => Auth::user()->username,
        ]);
        return back()->with('success', "IP '{$request->ip_address}' blocked successfully.");
    }

    public function unblockIp(BlockedIp $blockedIp)
    {
        $blockedIp->delete();
        return back()->with('success', 'IP unblocked successfully.');
    }

    public function allowedIps()
    {
        $allowedIps = AllowedIp::latest()->paginate(20);
        return view('security.allowed-ips', compact('allowedIps'));
    }

    public function allowIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'description' => 'nullable|string|max:255',
        ]);

        AllowedIp::create([
            ...$request->all(),
            'added_by' => Auth::user()->username,
        ]);
        return back()->with('success', "IP '{$request->ip_address}' added to allow list.");
    }

    public function removeAllowedIp(AllowedIp $allowedIp)
    {
        $allowedIp->delete();
        return back()->with('success', 'Allowed IP removed successfully.');
    }

    // CSF Firewall
    public function csf()
    {
        $status = SecurityService::csfStatus();
        $allowList = SecurityService::csfGetAllowList();
        $denyList = SecurityService::csfGetDenyList();
        return view('security.csf', compact('status', 'allowList', 'denyList'));
    }

    public function csfAction(Request $request)
    {
        $request->validate(['action' => 'required|in:enable,disable,restart,quick_r,flush_all,status,csf_update,csf_test']);
        $output = match ($request->action) {
            'enable' => SecurityService::csfEnable(),
            'disable' => SecurityService::csfDisable(),
            'restart' => SecurityService::csfRestart(),
            'quick_r' => SecurityService::csfQuickRestart(),
            'flush_all' => SecurityService::csfFlushAll(),
            'status' => SecurityService::csfStatus(),
            'csf_update' => SecurityService::csfUpdate(),
            'csf_test' => SecurityService::csfTest(),
            default => 'Invalid action',
        };
        return back()->with('output', $output);
    }

    public function csfAllowIp(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'comment' => 'nullable|string',
        ]);
        $output = SecurityService::csfAllowIp($request->ip, $request->comment ?? '');
        return back()->with('output', $output)->with('success', 'IP allowed.');
    }

    public function csfDenyIp(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'comment' => 'nullable|string',
        ]);
        $output = SecurityService::csfDenyIp($request->ip, $request->comment ?? '', $request->boolean('permanent'));
        return back()->with('output', $output)->with('success', 'IP denied.');
    }

    public function csfUnblockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $output = SecurityService::csfUnblockIp($request->ip);
        return back()->with('output', $output)->with('success', 'IP unblocked.');
    }

    public function csfConfig()
    {
        $conf = SecurityService::csfGetConf();
        return view('security.csf-config', compact('conf'));
    }

    public function csfSaveConfig(Request $request)
    {
        $request->validate(['content' => 'required|string']);
        SecurityService::csfSaveConf($request->content);
        return back()->with('success', 'CSF configuration saved.');
    }

    // ModSecurity
    public function modSecurity()
    {
        $status = SecurityService::getModSecurityStatus();
        $rules = SecurityService::getModSecurityRules();
        return view('security.mod-security', compact('status', 'rules'));
    }

    public function modSecurityToggle(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        if ($request->enabled) {
            SecurityService::modSecurityEnable();
        } else {
            SecurityService::modSecurityDisable();
        }
        return back()->with('success', 'ModSecurity toggled.');
    }

    public function modSecuritySaveRules(Request $request)
    {
        $request->validate(['content' => 'required|string']);
        SecurityService::saveModSecurityRules($request->content);
        return back()->with('success', 'ModSecurity rules saved.');
    }

    // Maldet
    public function maldet()
    {
        $installed = SecurityService::maldetIsInstalled();
        $scans = $installed ? SecurityService::maldetGetScans() : [];
        return view('security.maldet', compact('installed', 'scans'));
    }

    public function maldetAction(Request $request)
    {
        $request->validate(['action' => 'required|in:install,uninstall,update,scan_user']);
        $output = match ($request->action) {
            'install' => SecurityService::maldetInstall(),
            'uninstall' => SecurityService::maldetUninstall(),
            'update' => SecurityService::maldetUpdate(),
            'scan_user' => SecurityService::maldetScanUser($request->username ?? 'all'),
            default => 'Invalid',
        };
        return back()->with('output', $output)->with('success', 'Action performed.');
    }

    // RKHunter
    public function rkhunter()
    {
        $installed = SecurityService::rkhunterIsInstalled();
        $scans = $installed ? SecurityService::rkhunterGetScans() : [];
        return view('security.rkhunter', compact('installed', 'scans'));
    }

    public function rkhunterAction(Request $request)
    {
        $request->validate(['action' => 'required|in:install,uninstall,update,scan']);
        $output = match ($request->action) {
            'install' => SecurityService::rkhunterInstall(),
            'uninstall' => SecurityService::rkhunterUninstall(),
            'update' => SecurityService::rkhunterUpdate(),
            'scan' => SecurityService::rkhunterScan(),
            default => 'Invalid',
        };
        return back()->with('output', $output)->with('success', 'Action performed.');
    }

    // Lynis
    public function lynis()
    {
        $installed = SecurityService::lynisIsInstalled();
        $scans = $installed ? SecurityService::lynisGetScans() : [];
        return view('security.lynis', compact('installed', 'scans'));
    }

    public function lynisAction(Request $request)
    {
        $request->validate(['action' => 'required|in:install,uninstall,scan']);
        $output = match ($request->action) {
            'install' => SecurityService::lynisInstall(),
            'uninstall' => SecurityService::lynisUninstall(),
            'scan' => SecurityService::lynisScan(),
            default => 'Invalid',
        };
        return back()->with('output', $output)->with('success', 'Action performed.');
    }

    // Cgroups
    public function cgroups()
    {
        $status = SecurityService::cgroupsGetStatus();
        $conf = SecurityService::cgroupsGetConf();
        return view('security.cgroups', compact('status', 'conf'));
    }

    public function cgroupsAction(Request $request)
    {
        $request->validate(['action' => 'required|in:restart']);
        $output = SecurityService::cgroupsRestart();
        return back()->with('output', $output)->with('success', 'Cgroups restarted.');
    }

    public function cgroupsSetLimit(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'limits' => 'required|array',
        ]);
        SecurityService::cgroupsSetUserLimit($request->username, $request->limits);
        return back()->with('success', 'Cgroups limits set.');
    }

    // Login security
    public function loginSecurity()
    {
        $failedLogins = SecurityService::getFailedLogins();
        $loggedInUsers = SecurityService::getLoggedInUsers();
        return view('security.login-security', compact('failedLogins', 'loggedInUsers'));
    }

    // Shell access
    public function shellAccess()
    {
        $shells = SecurityService::getShells();
        return view('security.shell-access', compact('shells'));
    }

    public function setShell(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'shell' => 'required|string',
        ]);
        SecurityService::setUserShell($request->username, $request->shell);
        return back()->with('success', 'Shell updated.');
    }

    // Kernel
    public function kernel()
    {
        $info = SecurityService::getKernelInfo();
        return view('security.kernel', compact('info'));
    }

    public function kernelUpdate()
    {
        $output = SecurityService::kernelUpdate();
        return back()->with('output', $output)->with('success', 'Kernel update initiated.');
    }

    // Symlink scan
    public function symlinkScan(Request $request)
    {
        $path = $request->get('path', '/home');
        $results = SecurityService::symlinkScan($path);
        return view('security.symlink-scan', compact('results', 'path'));
    }

    // Iptables
    public function iptables()
    {
        $rules = SecurityService::iptablesList();
        return view('security.iptables', compact('rules'));
    }

    public function iptablesFlush()
    {
        $output = SecurityService::iptablesFlush();
        return back()->with('output', $output)->with('success', 'Iptables flushed.');
    }
}
