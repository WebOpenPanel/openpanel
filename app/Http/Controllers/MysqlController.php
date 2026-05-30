<?php

namespace App\Http\Controllers;

use App\Models\MysqlDatabase;
use App\Models\MysqlUser;
use App\Models\UserAccount;
use App\Services\MysqlService;
use Illuminate\Http\Request;

class MysqlController extends Controller
{
    public function index(Request $request)
    {
        $query = MysqlDatabase::with('userAccount');
        if ($request->filled('search')) {
            $query->where('database_name', 'like', "%{$request->search}%");
        }
        $databases = $query->latest()->paginate(20);
        $accounts = UserAccount::where('suspended', 'no')->orderBy('domain')->get();
        $serverDatabases = MysqlService::getDatabases();
        return view('mysql.index', compact('databases', 'accounts', 'serverDatabases'));
    }

    public function createDatabase(Request $request)
    {
        $request->validate([
            'user_account_id' => 'required|exists:user_accounts,id',
            'database_name' => 'required|string|max:64|regex:/^[a-zA-Z0-9_]+$/',
        ]);
        $account = UserAccount::find($request->user_account_id);
        $dbName = $account->id . '_' . $request->database_name;
        MysqlService::createDatabase($dbName);
        MysqlDatabase::create([
            'user_account_id' => $account->id,
            'database_name' => $dbName,
            'charset' => $request->charset ?? 'utf8mb4',
            'collation' => $request->collation ?? 'utf8mb4_unicode_ci',
        ]);
        return back()->with('success', "Database '{$dbName}' created.");
    }

    public function destroyDatabase(MysqlDatabase $database)
    {
        MysqlService::dropDatabase($database->database_name);
        $database->users()->detach();
        $database->delete();
        return back()->with('success', 'Database deleted.');
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'user_account_id' => 'required|exists:user_accounts,id',
            'username' => 'required|string|max:32|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $account = UserAccount::find($request->user_account_id);
        $dbUser = $account->id . '_' . $request->username;
        MysqlService::createDatabaseUser($dbUser, $request->password);
        MysqlUser::create([
            'user_account_id' => $account->id,
            'username' => $dbUser,
            'password_hash' => bcrypt($request->password),
        ]);
        return back()->with('success', "MySQL user '{$dbUser}' created.");
    }

    public function deleteUser(MysqlUser $mysqlUser)
    {
        MysqlService::dropDatabaseUser($mysqlUser->username);
        $mysqlUser->databases()->detach();
        $mysqlUser->delete();
        return back()->with('success', 'MySQL user deleted.');
    }

    public function assignUser(Request $request)
    {
        $request->validate([
            'mysql_user_id' => 'required|exists:mysql_users,id',
            'mysql_database_id' => 'required|exists:mysql_databases,id',
            'privileges' => 'required|array',
        ]);
        $db = MysqlDatabase::find($request->mysql_database_id);
        $user = MysqlUser::find($request->mysql_user_id);
        MysqlService::grantPrivileges($user->username, $db->database_name);
        $user->databases()->attach($request->mysql_database_id, ['privileges' => json_encode($request->privileges)]);
        return back()->with('success', 'User assigned to database.');
    }

    public function revokeUser(Request $request)
    {
        $request->validate([
            'mysql_user_id' => 'required|exists:mysql_users,id',
            'mysql_database_id' => 'required|exists:mysql_databases,id',
        ]);
        $db = MysqlDatabase::find($request->mysql_database_id);
        $user = MysqlUser::find($request->mysql_user_id);
        MysqlService::revokePrivileges($user->username, $db->database_name);
        $user->databases()->detach($request->mysql_database_id);
        return back()->with('success', 'User access revoked.');
    }

    public function status()
    {
        $status = MysqlService::getMysqlStatus();
        $serviceStatus = MysqlService::getServiceStatus();
        return view('mysql.status', compact('status', 'serviceStatus'));
    }

    public function processes()
    {
        $processes = MysqlService::getProcessList();
        return view('mysql.processes', compact('processes'));
    }

    public function killProcess(int $id)
    {
        MysqlService::killProcess($id);
        return back()->with('success', "Process {$id} killed.");
    }

    public function config()
    {
        $conf = MysqlService::getMysqlConf();
        return view('mysql.config', compact('conf'));
    }

    public function saveConfig(Request $request)
    {
        $request->validate(['content' => 'required|string']);
        MysqlService::saveMysqlConf($request->content);
        return back()->with('success', 'MySQL config saved.');
    }

    public function optimize()
    {
        $output = MysqlService::optimizeAllDatabases();
        return back()->with('output', $output)->with('success', 'Databases optimized.');
    }

    public function repair()
    {
        $output = MysqlService::repairAllDatabases();
        return back()->with('output', $output)->with('success', 'Databases repaired.');
    }

    public function postgresql()
    {
        $installed = MysqlService::pgsqlIsInstalled();
        $status = MysqlService::pgsqlGetStatus();
        $databases = $installed ? MysqlService::pgsqlGetDatabases() : ['raw' => ''];
        return view('mysql.postgresql', compact('installed', 'status', 'databases'));
    }

    public function mongodb()
    {
        $installed = MysqlService::mongodbIsInstalled();
        $status = MysqlService::mongodbGetStatus();
        $databases = $installed ? MysqlService::mongodbGetDatabases() : ['raw' => ''];
        return view('mysql.mongodb', compact('installed', 'status', 'databases'));
    }
}
