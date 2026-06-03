<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class MongoController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $installed = $this->isInstalled();
        $databases = $installed ? $this->listDatabases() : [];
        $version = $installed ? trim($this->process()->run("mongod --version | head -1")->output()) : null;
        $running = $installed ? $this->process()->run("systemctl is-active mongod")->successful() : false;

        return view('mongo.index', compact('installed', 'databases', 'version', 'running'));
    }

    public function install()
    {
        $repo = '[mongodb-org-7.0]
name=MongoDB Repository
baseurl=https://repo.mongodb.org/yum/redhat/9/mongodb-org/7.0/x86_64/
gpgcheck=1
enabled=1
gpgkey=https://pgp.mongodb.com/server-7.0.asc';

        $this->process()->run("cat > /etc/yum.repos.d/mongodb-org-7.0.repo <<'REPOEOF'\n{$repo}\nREPOEOF");
        $result = $this->process()->run("dnf -y install mongodb-org 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Install failed: ' . $result->errorOutput());
        }
        $this->process()->run("systemctl enable --now mongod");
        return back()->with('success', 'MongoDB installed.');
    }

    public function createDatabase(Request $request)
    {
        $request->validate(['name' => 'required|string|max:64']);
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $request->name);
        $result = $this->process()->run("mongosh --eval \"use {$name}; db.createCollection('init');\" 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Create failed: ' . $result->errorOutput());
        }
        return back()->with('success', "Database '{$name}' created.");
    }

    public function dropDatabase(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $request->name);
        $result = $this->process()->run("mongosh {$name} --eval \"db.dropDatabase()\" 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Drop failed: ' . $result->errorOutput());
        }
        return back()->with('success', "Database '{$name}' dropped.");
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:64',
            'password' => 'required|string|min:6',
            'database' => 'required|string',
        ]);
        $user = $request->username;
        $pass = $request->password;
        $db = preg_replace('/[^a-zA-Z0-9_]/', '', $request->database);
        $result = $this->process()->run("mongosh {$db} --eval \"db.createUser({{user:'{$user}',pwd:'{$pass}',roles:[{{role:'readWrite',db:'{$db}'}}]}})\" 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Create user failed: ' . $result->errorOutput());
        }
        return back()->with('success', "User '{$user}' created on '{$db}'.");
    }

    public function service(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:start,stop,restart,status'])['action'];
        $result = $this->process()->run("systemctl {$action} mongod 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', trim($result->output() . $result->errorOutput()));
    }

    protected function isInstalled(): bool
    {
        return $this->process()->run("which mongod 2>/dev/null")->successful();
    }

    protected function listDatabases(): array
    {
        $result = $this->process()->run("mongosh --eval \"db.adminCommand('listDatabases').databases.forEach(function(d){print(d.name)})\" 2>/dev/null");
        return array_filter(explode("\n", trim($result->output())));
    }
}
