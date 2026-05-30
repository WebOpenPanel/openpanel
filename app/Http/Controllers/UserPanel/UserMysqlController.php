<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserMysqlController extends Controller
{
    protected function username(): string
    {
        return auth()->user()->username;
    }

    public function index()
    {
        $username = $this->username();
        $databases = DB::connection('openpanel')->table('mysql_db')
            ->where('user', $username)
            ->get();

        $dbUsers = DB::connection('openpanel')->table('mysql_users')
            ->where('user', $username)
            ->get();

        return view('user-panel.mysql.index', compact('databases', 'dbUsers'));
    }

    public function phpmyadmin()
    {
        $username = $this->username();
        $token = base64_encode($username . ':' . now()->timestamp);
        $pmaUrl = "https://{$_SERVER['HTTP_HOST']}:2083/pma?token={$token}";

        return view('user-panel.mysql.phpmyadmin', compact('pmaUrl'));
    }

    public function createDatabase(Request $request)
    {
        $request->validate([
            'database' => 'required|string|regex:/^[a-zA-Z0-9_]+$/',
        ]);

        $username = $this->username();
        $db = $username . '_' . $request->database;

        ShellService::exec("mysql -e \"CREATE DATABASE IF NOT EXISTS `{$db}`\" 2>&1");

        DB::connection('openpanel')->table('mysql_db')->insert([
            'user' => $username,
            'database' => $db,
            'created_at' => now(),
        ]);

        return back()->with('success', "Database {$db} created.");
    }

    public function deleteDatabase(Request $request)
    {
        $request->validate(['database' => 'required|string']);

        $username = $this->username();
        $db = $request->database;

        $owned = DB::connection('openpanel')->table('mysql_db')
            ->where('user', $username)
            ->where('database', $db)
            ->exists();

        if (!$owned) {
            return back()->with('error', 'Database not found or not owned by you.');
        }

        ShellService::exec("mysql -e \"DROP DATABASE IF EXISTS `{$db}`\" 2>&1");
        DB::connection('openpanel')->table('mysql_db')->where('database', $db)->delete();

        return back()->with('success', "Database {$db} deleted.");
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'db_user' => 'required|string|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'required|string|min:6',
        ]);

        $username = $this->username();
        $dbUser = $username . '_' . $request->db_user;
        $password = $request->password;

        ShellService::exec("mysql -e \"CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY " . escapeshellarg($password) . "\" 2>&1");

        DB::connection('openpanel')->table('mysql_users')->insert([
            'user' => $username,
            'db_user' => $dbUser,
            'created_at' => now(),
        ]);

        return back()->with('success', "Database user {$dbUser} created.");
    }

    public function deleteUser(Request $request)
    {
        $request->validate(['db_user' => 'required|string']);

        $username = $this->username();
        $dbUser = $request->db_user;

        $owned = DB::connection('openpanel')->table('mysql_users')
            ->where('user', $username)
            ->where('db_user', $dbUser)
            ->exists();

        if (!$owned) {
            return back()->with('error', 'User not found or not owned by you.');
        }

        ShellService::exec("mysql -e \"DROP USER IF EXISTS '{$dbUser}'@'localhost'\" 2>&1");
        DB::connection('openpanel')->table('mysql_users')->where('db_user', $dbUser)->delete();

        return back()->with('success', "Database user {$dbUser} deleted.");
    }

    public function assignUser(Request $request)
    {
        $request->validate([
            'database' => 'required|string',
            'db_user' => 'required|string',
            'privileges' => 'string',
        ]);

        $username = $this->username();
        $db = $request->database;
        $dbUser = $request->db_user;
        $privileges = $request->privileges ?? 'ALL PRIVILEGES';

        $ownedDb = DB::connection('openpanel')->table('mysql_db')->where('user', $username)->where('database', $db)->exists();
        $ownedUser = DB::connection('openpanel')->table('mysql_users')->where('user', $username)->where('db_user', $dbUser)->exists();

        if (!$ownedDb || !$ownedUser) {
            return back()->with('error', 'Database or user not owned by you.');
        }

        ShellService::exec("mysql -e \"GRANT {$privileges} ON `{$db}`.* TO '{$dbUser}'@'localhost'; FLUSH PRIVILEGES;\" 2>&1");

        return back()->with('success', "Granted {$privileges} on {$db} to {$dbUser}.");
    }

    public function revokeUser(Request $request)
    {
        $request->validate([
            'database' => 'required|string',
            'db_user' => 'required|string',
        ]);

        $username = $this->username();
        $db = $request->database;
        $dbUser = $request->db_user;

        $ownedDb = DB::connection('openpanel')->table('mysql_db')->where('user', $username)->where('database', $db)->exists();
        $ownedUser = DB::connection('openpanel')->table('mysql_users')->where('user', $username)->where('db_user', $dbUser)->exists();

        if (!$ownedDb || !$ownedUser) {
            return back()->with('error', 'Database or user not owned by you.');
        }

        ShellService::exec("mysql -e \"REVOKE ALL PRIVILEGES ON `{$db}`.* FROM '{$dbUser}'@'localhost'; FLUSH PRIVILEGES;\" 2>&1");

        return back()->with('success', "Revoked all privileges on {$db} from {$dbUser}.");
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'db_user' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $username = $this->username();
        $dbUser = $request->db_user;

        $owned = DB::connection('openpanel')->table('mysql_users')->where('user', $username)->where('db_user', $dbUser)->exists();
        if (!$owned) {
            return back()->with('error', 'User not owned by you.');
        }

        ShellService::exec("mysql -e \"ALTER USER '{$dbUser}'@'localhost' IDENTIFIED BY " . escapeshellarg($request->password) . "; FLUSH PRIVILEGES;\" 2>&1");

        return back()->with('success', "Password changed for {$dbUser}.");
    }
}
