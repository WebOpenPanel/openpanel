<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class TomcatController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $installed = $this->isInstalled();
        $version = $installed ? trim($this->process()->run("catalina.sh version 2>/dev/null | head -1")->output()) : null;
        $running = $installed ? $this->process()->run("systemctl is-active tomcat")->successful() : false;
        $apps = $installed ? $this->listApps() : [];

        return view('tomcat.index', compact('installed', 'version', 'running', 'apps'));
    }

    public function install()
    {
        $result = $this->process()->run("dnf -y install tomcat tomcat-webapps tomcat-admin-webapps java-17-openjdk 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Install failed: ' . $result->errorOutput());
        }
        $this->process()->run("systemctl enable --now tomcat");
        return back()->with('success', 'Tomcat installed.');
    }

    public function deploy(Request $request)
    {
        $request->validate([
            'war' => 'required|file|mimes:war,zip|max:512000',
            'context' => 'required|string|max:64',
        ]);

        $context = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->context);
        $path = "/var/lib/tomcat/webapps/{$context}.war";
        $request->file('war')->move('/var/lib/tomcat/webapps', "{$context}.war");

        return back()->with('success', "WAR deployed as '/{$context}'.");
    }

    public function undeploy(Request $request)
    {
        $request->validate(['context' => 'required|string']);
        $context = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->context);
        @unlink("/var/lib/tomcat/webapps/{$context}.war");
        $this->process()->run("rm -rf /var/lib/tomcat/webapps/{$context}");
        return back()->with('success', "App '/{$context}' undeployed.");
    }

    public function service(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:start,stop,restart,status'])['action'];
        $result = $this->process()->run("systemctl {$action} tomcat 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', trim($result->output() . $result->errorOutput()));
    }

    public function editConfig()
    {
        $config = file_get_contents('/etc/tomcat/server.xml') ?: '';
        return view('tomcat.config', compact('config'));
    }

    public function saveConfig(Request $request)
    {
        $request->validate(['config' => 'required|string']);
        file_put_contents('/etc/tomcat/server.xml', $request->config);
        $this->process()->run("systemctl restart tomcat");
        return back()->with('success', 'Config saved.');
    }

    protected function isInstalled(): bool
    {
        return $this->process()->run("which catalina.sh 2>/dev/null")->successful() || is_dir('/etc/tomcat');
    }

    protected function listApps(): array
    {
        $dir = '/var/lib/tomcat/webapps';
        if (!is_dir($dir)) return [];
        return array_values(array_diff(scandir($dir), ['.', '..', 'ROOT', 'host-manager', 'manager']));
    }
}
