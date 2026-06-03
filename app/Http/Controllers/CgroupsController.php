<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class CgroupsController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $installed = $this->isInstalled();
        $groups = $installed ? $this->listGroups() : [];
        $config = $installed ? $this->getConfig() : '';

        return view('cgroups.index', compact('installed', 'groups', 'config'));
    }

    public function install()
    {
        $result = $this->process()->run("dnf -y install libcgroup libcgroup-tools 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Install failed: ' . $result->errorOutput());
        }
        $this->process()->run("systemctl enable --now cgconfig");
        return back()->with('success', 'CGroups installed.');
    }

    public function createGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:64',
            'cpu_shares' => 'nullable|integer|min:2|max:1024',
            'memory_limit' => 'nullable|string',
        ]);

        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->name);
        $cpu = $request->cpu_shares ?? 1024;
        $mem = $request->memory_limit ?? '512M';

        $config = <<<CGROUP
group {$name} {
    cpu {
        cpu.shares = {$cpu};
    }
    memory {
        memory.limit_in_bytes = {$mem};
    }
}
CGROUP;

        file_put_contents("/etc/cgconfig.d/{$name}.conf", $config);
        $this->process()->run("systemctl restart cgconfig");
        return back()->with('success', "CGroup '{$name}' created.");
    }

    public function deleteGroup(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->name);
        @unlink("/etc/cgconfig.d/{$name}.conf");
        $this->process()->run("systemctl restart cgconfig");
        return back()->with('success', "CGroup '{$name}' deleted.");
    }

    public function assignUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'group' => 'required|string',
        ]);
        $user = $request->username;
        $group = $request->group;

        $config = <<<RULE
{$group} {
    uid {$user};
}
RULE;

        file_put_contents("/etc/cgrules.d/{$user}.conf", $config);
        $this->process()->run("systemctl restart cgred");
        return back()->with('success', "'{$user}' assigned to '{$group}'.");
    }

    protected function isInstalled(): bool
    {
        return $this->process()->run("which cgcreate 2>/dev/null")->successful();
    }

    protected function listGroups(): array
    {
        $dir = '/etc/cgconfig.d';
        if (!is_dir($dir)) return [];
        return array_values(array_diff(scandir($dir), ['.', '..']));
    }

    protected function getConfig(): string
    {
        return file_get_contents('/etc/cgconfig.conf') ?: '';
    }
}
