<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserMysqlController extends Controller
{
    protected function username(): string
    {
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    protected function accountId(): ?int
    {
        $account = DB::table('accounts')->where('username', $this->username())->first();
        return $account?->id;
    }

    public function index()
    {
        $id = $this->accountId();
        $databases = $id ? DB::table('mysql_databases')->where('user_account_id', $id)->get() : collect();
        $users = $id ? DB::table('mysql_users')->where('user_account_id', $id)->get() : collect();
        return view('user-panel.mysql.index', compact('databases', 'users'));
    }

    public function createDatabase(Request $request)
    {
        $request->validate(['name' => 'required|string|alpha_dash']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $dbName = $this->username() . '_' . $request->name;
        DB::statement("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $dbName) . "`");
        DB::table('mysql_databases')->insert([
            'user_account_id' => $id,
            'name' => $dbName,
            'charset' => 'utf8mb4',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', "Database {$dbName} created.");
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|alpha_dash',
            'password' => 'required|string|min:6',
        ]);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $dbUser = $this->username() . '_' . $request->username;
        DB::statement("CREATE USER IF NOT EXISTS '" . str_replace("'", '', $dbUser) . "'@'localhost' IDENTIFIED BY '" . str_replace("'", '', $request->password) . "'");
        DB::table('mysql_users')->insert([
            'user_account_id' => $id,
            'username' => $dbUser,
            'host' => 'localhost',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', "MySQL user {$dbUser} created.");
    }

    public function deleteDatabase(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $db = DB::table('mysql_databases')->where('id', $request->id)->where('user_account_id', $id)->first();
        if ($db) {
            DB::statement("DROP DATABASE IF EXISTS `" . str_replace('`', '', $db->name) . "`");
            DB::table('mysql_databases')->where('id', $db->id)->delete();
        }
        return back()->with('success', 'Database deleted.');
    }

    public function deleteUser(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $id = $this->accountId();
        if (!$id) return back()->with('error', 'Account not found.');

        $user = DB::table('mysql_users')->where('id', $request->id)->where('user_account_id', $id)->first();
        if ($user) {
            DB::statement("DROP USER IF EXISTS '" . str_replace("'", '', $user->username) . "'@'" . str_replace("'", '', $user->host) . "'");
            DB::table('mysql_users')->where('id', $user->id)->delete();
        }
        return back()->with('success', 'MySQL user deleted.');
    }

    public function phpmyadmin()
    {
        $pmaUrl = config('openpanel.phpmyadmin_url', '/phpmyadmin');
        return view('user-panel.mysql.phpmyadmin', compact('pmaUrl'));
    }
}
