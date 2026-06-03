<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class PostgresqlController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }
    public function index()
    {
        $installed = $this->isInstalled();
        $databases = $installed ? $this->listDatabases() : [];
        $users = $installed ? $this->listUsers() : [];
        $version = $installed ? trim($this->process()->run("psql --version | awk '{print $3}'")->output()) : null;
        $running = $installed ? $this->process()->run("systemctl is-active postgresql")->successful() : false;

        return view('postgresql.index', compact('installed', 'databases', 'users', 'version', 'running'));
    }

    public function install()
    {
        $result = $this->process()->run("dnf -y install postgresql-server postgresql 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Install failed: ' . $result->errorOutput());
        }
        $this->process()->run("postgresql-setup --initdb 2>&1");
        $this->process()->run("systemctl enable --now postgresql");
        return back()->with('success', 'PostgreSQL installed.');
    }

    public function createDatabase(Request $request)
    {
        $request->validate(['name' => 'required|string|max:63']);
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $request->name);
        $result = $this->process()->run("sudo -u postgres createdb {$name} 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Create failed: ' . $result->errorOutput());
        }
        return back()->with('success', "Database '{$name}' created.");
    }

    public function dropDatabase(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $request->name);
        $result = $this->process()->run("sudo -u postgres dropdb {$name} 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Drop failed: ' . $result->errorOutput());
        }
        return back()->with('success', "Database '{$name}' dropped.");
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:63',
            'password' => 'required|string|min:6',
        ]);
        $user = preg_replace('/[^a-zA-Z0-9_]/', '', $request->username);
        $pass = escapeshellarg($request->password);
        $result = $this->process()->run("sudo -u postgres psql -c \"CREATE USER {$user} WITH PASSWORD {$pass};\" 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Create user failed: ' . $result->errorOutput());
        }
        return back()->with('success', "User '{$user}' created.");
    }

    public function dropUser(Request $request)
    {
        $request->validate(['username' => 'required|string']);
        $user = preg_replace('/[^a-zA-Z0-9_]/', '', $request->username);
        $result = $this->process()->run("sudo -u postgres psql -c \"DROP USER {$user};\" 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Drop user failed: ' . $result->errorOutput());
        }
        return back()->with('success', "User '{$user}' dropped.");
    }

    public function grant(Request $request)
    {
        $request->validate([
            'database' => 'required|string',
            'username' => 'required|string',
        ]);
        $db = preg_replace('/[^a-zA-Z0-9_]/', '', $request->database);
        $user = preg_replace('/[^a-zA-Z0-9_]/', '', $request->username);
        $result = $this->process()->run("sudo -u postgres psql -c \"GRANT ALL PRIVILEGES ON DATABASE {$db} TO {$user};\" 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Grant failed: ' . $result->errorOutput());
        }
        return back()->with('success', "Granted all on '{$db}' to '{$user}'.");
    }

    public function service(Request $request)
    {
        $action = $request->validate(['action' => 'required|in:start,stop,restart,status'])['action'];
        $result = $this->process()->run("systemctl {$action} postgresql 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', trim($result->output() . $result->errorOutput()));
    }

    protected function isInstalled(): bool
    {
        return $this->process()->run("which psql 2>/dev/null")->successful();
    }

    protected function listDatabases(): array
    {
        $result = $this->process()->run("sudo -u postgres psql -t -A -c \"SELECT datname FROM pg_database WHERE datistemplate = false;\" 2>/dev/null");
        return array_filter(explode("\n", trim($result->output())));
    }

    protected function listUsers(): array
    {
        $result = $this->process()->run("sudo -u postgres psql -t -A -c \"SELECT usename FROM pg_user;\" 2>/dev/null");
        return array_filter(explode("\n", trim($result->output())));
    }
}
