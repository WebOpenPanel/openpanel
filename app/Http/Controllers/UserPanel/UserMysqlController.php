<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\MysqlService;
use App\Services\PhpMyAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserMysqlController extends Controller
{
    protected function username(): string
    {
        return Auth::user()->username;
    }

    protected function account(): ?object
    {
        return DB::table('accounts')->where('username', $this->username())->first();
    }

    protected function accountId(): ?int
    {
        return $this->account()?->id;
    }

    protected function ownedQuery(string $table)
    {
        $query = DB::table($table);
        $accountId = $this->accountId();

        if (!$accountId) {
            return $query->whereRaw('1 = 0');
        }

        if (Schema::hasColumn($table, 'account_id')) {
            return $query->where('account_id', $accountId);
        }

        return $query->where('user_account_id', $accountId);
    }

    protected function ownershipColumns(string $table): array
    {
        $accountId = $this->accountId();
        if (Schema::hasColumn($table, 'account_id')) {
            return ['account_id' => $accountId, 'user_account_id' => null];
        }

        return ['user_account_id' => $accountId];
    }

    public function index()
    {
        $databases = $this->ownedQuery('mysql_databases')->whereNull('deleted_at')->orderBy('database_name')->get();
        $users = $this->ownedQuery('mysql_users')->whereNull('deleted_at')->orderBy('username')->get();
        $grants = DB::table('mysql_user_database')
            ->join('mysql_users', 'mysql_user_database.mysql_user_id', '=', 'mysql_users.id')
            ->join('mysql_databases', 'mysql_user_database.mysql_database_id', '=', 'mysql_databases.id')
            ->whereNull('mysql_users.deleted_at')
            ->whereNull('mysql_databases.deleted_at');

        if (Schema::hasColumn('mysql_users', 'account_id')) {
            $grants->where('mysql_users.account_id', $this->accountId());
        } else {
            $grants->where('mysql_users.user_account_id', $this->accountId());
        }

        $assignments = $grants
            ->select(
                'mysql_user_database.id',
                'mysql_users.id as mysql_user_id',
                'mysql_databases.id as mysql_database_id',
                'mysql_users.username',
                'mysql_databases.database_name'
            )
            ->orderBy('mysql_users.username')
            ->get();

        return view('user-panel.mysql.index', compact('databases', 'users', 'assignments'));
    }

    public function createDatabase(Request $request)
    {
        $request->validate(['name' => 'required|string|alpha_dash|max:48']);
        if (!$this->accountId()) return back()->with('error', 'Account not found.');

        $dbName = $this->username() . '_' . $request->name;
        MysqlService::createDatabase($dbName);

        DB::table('mysql_databases')->insert(array_merge($this->ownershipColumns('mysql_databases'), [
            'database_name' => $dbName,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'size_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return back()->with('success', "Database {$dbName} created.");
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|alpha_dash|max:48',
            'password' => 'required|string|min:8',
            'database_id' => 'nullable|integer',
        ]);
        if (!$this->accountId()) return back()->with('error', 'Account not found.');

        $dbUser = $this->username() . '_' . $request->username;
        MysqlService::createDatabaseUser($dbUser, $request->password);

        $mysqlUserId = DB::table('mysql_users')->insertGetId(array_merge($this->ownershipColumns('mysql_users'), [
            'username' => $dbUser,
            'password_hash' => password_hash($request->password, PASSWORD_DEFAULT),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        if ($request->filled('database_id')) {
            $db = $this->ownedQuery('mysql_databases')->where('id', $request->integer('database_id'))->whereNull('deleted_at')->first();
            if ($db) {
                MysqlService::grantPrivileges($dbUser, $db->database_name);
                DB::table('mysql_user_database')->updateOrInsert(
                    ['mysql_user_id' => $mysqlUserId, 'mysql_database_id' => $db->id],
                    ['privileges' => json_encode(['ALL']), 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        return back()->with('success', "MySQL user {$dbUser} created.");
    }

    public function deleteDatabase(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $db = $this->ownedQuery('mysql_databases')->where('id', $request->integer('id'))->whereNull('deleted_at')->first();
        if ($db) {
            MysqlService::dropDatabase($db->database_name);
            DB::table('mysql_user_database')->where('mysql_database_id', $db->id)->delete();
            DB::table('mysql_databases')->where('id', $db->id)->delete();
        }

        return back()->with('success', 'Database deleted.');
    }

    public function deleteUser(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $user = $this->ownedQuery('mysql_users')->where('id', $request->integer('id'))->whereNull('deleted_at')->first();
        if ($user) {
            MysqlService::dropDatabaseUser($user->username);
            DB::table('mysql_user_database')->where('mysql_user_id', $user->id)->delete();
            DB::table('mysql_users')->where('id', $user->id)->delete();
        }

        return back()->with('success', 'MySQL user deleted.');
    }

    public function assignUser(Request $request)
    {
        $request->validate([
            'mysql_user_id' => 'required|integer',
            'mysql_database_id' => 'required|integer',
        ]);

        $user = $this->ownedQuery('mysql_users')->where('id', $request->integer('mysql_user_id'))->whereNull('deleted_at')->first();
        $db = $this->ownedQuery('mysql_databases')->where('id', $request->integer('mysql_database_id'))->whereNull('deleted_at')->first();
        if (!$user || !$db) return back()->with('error', 'Database or user not found.');

        MysqlService::grantPrivileges($user->username, $db->database_name);
        DB::table('mysql_user_database')->updateOrInsert(
            ['mysql_user_id' => $user->id, 'mysql_database_id' => $db->id],
            ['privileges' => json_encode(['ALL']), 'updated_at' => now(), 'created_at' => now()]
        );

        return back()->with('success', 'Database access granted.');
    }

    public function revokeUser(Request $request)
    {
        $request->validate([
            'mysql_user_id' => 'required|integer',
            'mysql_database_id' => 'required|integer',
        ]);

        $user = $this->ownedQuery('mysql_users')->where('id', $request->integer('mysql_user_id'))->whereNull('deleted_at')->first();
        $db = $this->ownedQuery('mysql_databases')->where('id', $request->integer('mysql_database_id'))->whereNull('deleted_at')->first();
        if (!$user || !$db) return back()->with('error', 'Database or user not found.');

        MysqlService::revokePrivileges($user->username, $db->database_name);
        DB::table('mysql_user_database')->where('mysql_user_id', $user->id)->where('mysql_database_id', $db->id)->delete();

        return back()->with('success', 'Database access revoked.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'mysql_user_id' => 'required|integer',
            'password' => 'required|string|min:8',
        ]);

        $user = $this->ownedQuery('mysql_users')->where('id', $request->integer('mysql_user_id'))->whereNull('deleted_at')->first();
        if (!$user) return back()->with('error', 'MySQL user not found.');

        MysqlService::changeUserPassword($user->username, $request->password);
        DB::table('mysql_users')->where('id', $user->id)->update([
            'password_hash' => password_hash($request->password, PASSWORD_DEFAULT),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'MySQL password changed.');
    }

    public function phpmyadmin()
    {
        $pmaUrl = PhpMyAdminService::url();
        $pmaStatus = PhpMyAdminService::status();

        return view('user-panel.mysql.phpmyadmin', compact('pmaUrl', 'pmaStatus'));
    }
}
