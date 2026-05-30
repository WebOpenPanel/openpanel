<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MysqlService
{
    const MYSQL_CONF = '/etc/my.cnf';
    const MARIADB_CONF = '/etc/my.cnf.d/server.cnf';

    public static function getRootPassword(): string
    {
        $content = ShellService::readFile('/root/.my.cnf');
        if (preg_match('/password\s*=\s*(\S+)/', $content, $m)) {
            return trim($m[1]);
        }
        return '123456';
    }

    public static function getConnection()
    {
        return DB::connection('mysql');
    }

    public static function getDatabases(): array
    {
        try {
            $dbs = DB::select('SHOW DATABASES');
            $result = [];
            foreach ($dbs as $db) {
                $name = $db->Database ?? '';
                if (in_array($name, ['information_schema', 'performance_schema', 'sys'])) continue;
                $size = self::getDatabaseSize($name);
                $result[] = ['name' => $name, 'size' => $size];
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getDatabaseSize(string $database): string
    {
        try {
            $result = DB::select(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = ?",
                [$database]
            );
            return ($result[0]->size ?? 0) . ' MB';
        } catch (\Exception $e) {
            return '0 MB';
        }
    }

    public static function createDatabase(string $name): bool
    {
        DB::statement('CREATE DATABASE IF NOT EXISTS `' . $name . '`');
        return true;
    }

    public static function dropDatabase(string $name): bool
    {
        DB::statement('DROP DATABASE IF EXISTS `' . $name . '`');
        return true;
    }

    public static function getDatabaseUsers(): array
    {
        try {
            return DB::select("SELECT User, Host FROM mysql.user ORDER BY User");
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function createDatabaseUser(string $username, string $password, string $host = 'localhost'): bool
    {
        DB::statement("CREATE USER IF NOT EXISTS '" . $username . "'@'" . $host . "' IDENTIFIED BY ?", [$password]);
        return true;
    }

    public static function dropDatabaseUser(string $username, string $host = 'localhost'): bool
    {
        DB::statement("DROP USER IF EXISTS '" . $username . "'@'" . $host . "'");
        return true;
    }

    public static function grantPrivileges(string $username, string $database, string $host = 'localhost'): bool
    {
        DB::statement("GRANT ALL PRIVILEGES ON `" . $database . "`.* TO '" . $username . "'@'" . $host . "'");
        DB::statement('FLUSH PRIVILEGES');
        return true;
    }

    public static function revokePrivileges(string $username, string $database, string $host = 'localhost'): bool
    {
        DB::statement("REVOKE ALL PRIVILEGES ON `" . $database . "`.* FROM '" . $username . "'@'" . $host . "'");
        DB::statement('FLUSH PRIVILEGES');
        return true;
    }

    public static function changeUserPassword(string $username, string $password, string $host = 'localhost'): bool
    {
        DB::statement("ALTER USER '" . $username . "'@'" . $host . "' IDENTIFIED BY ?", [$password]);
        DB::statement('FLUSH PRIVILEGES');
        return true;
    }

    public static function getUserGrants(string $username, string $host = 'localhost'): array
    {
        try {
            return DB::select("SHOW GRANTS FOR '" . $username . "'@'" . $host . "'");
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getTables(string $database): array
    {
        try {
            $tables = DB::select("SELECT table_name, table_rows, ROUND(data_length/1024/1024,2) as data_size, ROUND(index_length/1024/1024,2) as index_size, engine FROM information_schema.tables WHERE table_schema = ?", [$database]);
            return $tables;
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function optimizeTable(string $database, string $table): string
    {
        try {
            DB::statement("OPTIMIZE TABLE `{$database}`.`{$table}`");
            return 'Table optimized successfully';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public static function repairTable(string $database, string $table): string
    {
        try {
            DB::statement("REPAIR TABLE `{$database}`.`{$table}`");
            return 'Table repaired successfully';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public static function getMysqlConf(): string
    {
        return ShellService::readFile(self::MYSQL_CONF);
    }

    public static function saveMysqlConf(string $content): bool
    {
        ShellService::writeFile(self::MYSQL_CONF, $content);
        ServerService::serviceAction('restart', 'mysqld');
        return true;
    }

    public static function getMysqlStatus(): array
    {
        try {
            $status = DB::select('SHOW GLOBAL STATUS');
            $variables = DB::select('SHOW GLOBAL VARIABLES');
            $statusArr = [];
            foreach ($status as $row) {
                $statusArr[$row->Variable_name] = $row->Value;
            }
            $varArr = [];
            foreach ($variables as $row) {
                $varArr[$row->Variable_name] = $row->Value;
            }
            return ['status' => $statusArr, 'variables' => $varArr];
        } catch (\Exception $e) {
            return ['status' => [], 'variables' => []];
        }
    }

    public static function getProcessList(): array
    {
        try {
            return DB::select('SHOW PROCESSLIST');
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function killProcess(int $id): bool
    {
        try {
            DB::statement("KILL " . (int) $id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function dumpDatabase(string $database, string $outputPath): bool
    {
        $password = self::getRootPassword();
        ShellService::exec("mysqldump -u root -p'{$password}' " . escapeshellarg($database) . " > " . escapeshellarg($outputPath) . " 2>&1");
        return file_exists($outputPath) && filesize($outputPath) > 0;
    }

    public static function importDatabase(string $database, string $sqlPath): string
    {
        $password = self::getRootPassword();
        return ShellService::exec("mysql -u root -p'{$password}' " . escapeshellarg($database) . " < " . escapeshellarg($sqlPath) . " 2>&1");
    }

    public static function optimizeAllDatabases(): string
    {
        $password = self::getRootPassword();
        return ShellService::exec("mysqlcheck -u root -p'{$password}' --optimize --all-databases 2>&1");
    }

    public static function repairAllDatabases(): string
    {
        $password = self::getRootPassword();
        return ShellService::exec("mysqlcheck -u root -p'{$password}' --repair --all-databases 2>&1");
    }

    // PostgreSQL
    public static function pgsqlIsInstalled(): bool
    {
        return !empty(ShellService::exec('which psql 2>/dev/null'));
    }

    public static function pgsqlGetStatus(): array
    {
        $status = ShellService::exec('systemctl is-active postgresql 2>/dev/null');
        return ['active' => trim($status) === 'active'];
    }

    public static function pgsqlAction(string $action): string
    {
        return ServerService::serviceAction($action, 'postgresql');
    }

    public static function pgsqlGetDatabases(): array
    {
        $output = ShellService::exec("su - postgres -c 'psql -l' 2>/dev/null");
        return ['raw' => $output];
    }

    // MongoDB
    public static function mongodbIsInstalled(): bool
    {
        return !empty(ShellService::exec('which mongod 2>/dev/null'));
    }

    public static function mongodbGetStatus(): array
    {
        $status = ShellService::exec('systemctl is-active mongod 2>/dev/null');
        return ['active' => trim($status) === 'active'];
    }

    public static function mongodbAction(string $action): string
    {
        return ServerService::serviceAction($action, 'mongod');
    }

    public static function mongodbGetDatabases(): array
    {
        $output = ShellService::exec("mongo --eval 'show dbs' 2>/dev/null || mongosh --eval 'show dbs' 2>/dev/null");
        return ['raw' => $output];
    }

    // phpMyAdmin
    public static function phpMyAdminIsInstalled(): bool
    {
        return file_exists('/usr/local/openpanel/var/services/pma') || file_exists('/var/www/html/phpmyadmin');
    }

    public static function phpMyAdminAutologin(): string
    {
        $password = self::getRootPassword();
        return ShellService::exec("mysql -u root -p'{$password}' -e 'SELECT 1' 2>/dev/null && echo 'OK'");
    }

    // MySQL/MariaDB service
    public static function getServiceStatus(): array
    {
        $mysql = ShellService::exec('systemctl is-active mysqld 2>/dev/null');
        if (trim($mysql) !== 'active') {
            $mysql = ShellService::exec('systemctl is-active mariadb 2>/dev/null');
        }
        return ['active' => trim($mysql) === 'active'];
    }

    public static function serviceAction(string $action): string
    {
        $output = ServerService::serviceAction($action, 'mysqld');
        if (strpos($output, 'not found') !== false || strpos($output, 'Failed') !== false) {
            $output = ServerService::serviceAction($action, 'mariadb');
        }
        return $output;
    }

    public static function getRemoteAccess(): bool
    {
        $conf = self::getMysqlConf();
        return (bool) preg_match('/bind-address\s*=\s*0\.0\.0\.0/', $conf);
    }

    public static function enableRemoteAccess(): bool
    {
        $conf = self::getMysqlConf();
        if (preg_match('/bind-address\s*=/', $conf)) {
            $conf = preg_replace('/bind-address\s*=\s*\S+/', 'bind-address = 0.0.0.0', $conf);
        } else {
            $conf .= "\n[mysqld]\nbind-address = 0.0.0.0\n";
        }
        self::saveMysqlConf($conf);
        return true;
    }

    public static function disableRemoteAccess(): bool
    {
        $conf = self::getMysqlConf();
        $conf = preg_replace('/bind-address\s*=\s*\S+/', 'bind-address = 127.0.0.1', $conf);
        self::saveMysqlConf($conf);
        return true;
    }

    // Fine-grained privilege management (ported from UserPrivilegiesTrait)
    const PRIVILEGES = [
        'Select', 'Insert', 'Update', 'Delete', 'Create', 'Drop',
        'Reload', 'Shutdown', 'Process', 'File', 'Grant', 'References',
        'Index', 'Alter', 'Show_db', 'Super', 'Create_tmp_table',
        'Lock_tables', 'Execute', 'Repl_slave', 'Repl_client',
        'Create_view', 'Show_view', 'Create_routine', 'Alter_routine',
        'Create_user', 'Event', 'Trigger', 'Create_tablespace',
    ];

    public static function grantSpecificPrivileges(string $username, string $database, array $privileges, string $host = 'localhost'): bool
    {
        $valid = array_intersect($privileges, self::PRIVILEGES);
        if (empty($valid)) return false;
        $privStr = implode(', ', $valid);
        DB::statement("GRANT {$privStr} ON `{$database}`.* TO '{$username}'@'{$host}'");
        DB::statement('FLUSH PRIVILEGES');
        return true;
    }

    public static function setResourceLimits(string $username, array $limits, string $host = 'localhost'): bool
    {
        $clauses = [];
        if (isset($limits['max_queries_per_hour'])) $clauses[] = "MAX_QUERIES_PER_HOUR " . (int) $limits['max_queries_per_hour'];
        if (isset($limits['max_updates_per_hour'])) $clauses[] = "MAX_UPDATES_PER_HOUR " . (int) $limits['max_updates_per_hour'];
        if (isset($limits['max_connections_per_hour'])) $clauses[] = "MAX_CONNECTIONS_PER_HOUR " . (int) $limits['max_connections_per_hour'];
        if (isset($limits['max_user_connections'])) $clauses[] = "MAX_USER_CONNECTIONS " . (int) $limits['max_user_connections'];
        if (empty($clauses)) return false;
        DB::statement("ALTER USER '{$username}'@'{$host}' WITH " . implode(' ', $clauses));
        return true;
    }

    // Database disk usage caching (ported from MySQLConsumptionTrait)
    public static function getDatabaseDiskUsage(string $database): int
    {
        $result = DB::select(
            "SELECT SUM(data_length + index_length) AS bytes FROM information_schema.tables WHERE table_schema = ?",
            [$database]
        );
        return (int) ($result[0]->bytes ?? 0);
    }

    public static function getAllDatabasesDiskUsage(): array
    {
        $result = DB::select(
            "SELECT table_schema AS db, SUM(data_length + index_length) AS bytes FROM information_schema.tables GROUP BY table_schema ORDER BY bytes DESC"
        );
        $usage = [];
        foreach ($result as $row) {
            $usage[$row->db] = (int) $row->bytes;
        }
        return $usage;
    }

    // Per-database operations (ported from OperateDBTrait)
    public static function optimizeDatabase(string $database): string
    {
        $password = self::getRootPassword();
        return ShellService::exec("mysqlcheck -u root -p'{$password}' --optimize " . escapeshellarg($database) . " 2>&1");
    }

    public static function checkDatabase(string $database): string
    {
        $password = self::getRootPassword();
        return ShellService::exec("mysqlcheck -u root -p'{$password}' --check " . escapeshellarg($database) . " 2>&1");
    }

    public static function repairDatabase(string $database): string
    {
        $password = self::getRootPassword();
        return ShellService::exec("mysqlcheck -u root -p'{$password}' --repair " . escapeshellarg($database) . " 2>&1");
    }

    public static function backupDatabase(string $database, ?string $outputPath = null): string
    {
        $outputPath = $outputPath ?: "/backup/mysql/{$database}_" . date('Y-m-d_H-i-s') . ".sql.gz";
        $dir = dirname($outputPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $password = self::getRootPassword();
        ShellService::exec("mysqldump -u root -p'{$password}' " . escapeshellarg($database) . " 2>/dev/null | gzip > " . escapeshellarg($outputPath));
        return $outputPath;
    }

    // MongoDB CRUD (ported from DatabaseTrait)
    public static function mongodbCreateDatabase(string $name, string $user = '', string $password = ''): string
    {
        $cmd = "db = db.getSiblingDB('{$name}')";
        if ($user && $password) {
            $cmd .= "; db.createUser({user:'{$user}',pwd:'{$password}',roles:[{role:'dbOwner',db:'{$name}'}]})";
        }
        return ShellService::exec("mongo --eval '{$cmd}' 2>/dev/null || mongosh --eval '{$cmd}' 2>/dev/null");
    }

    public static function mongodbDeleteDatabase(string $name): string
    {
        return ShellService::exec("mongo --eval 'db=db.getSiblingDB(\"{$name}\"); db.dropDatabase()' 2>/dev/null || mongosh --eval 'db=db.getSiblingDB(\"{$name}\"); db.dropDatabase()' 2>/dev/null");
    }

    public static function mongodbListDatabases(): array
    {
        $output = ShellService::exec("mongo --eval 'db.adminCommand(\"listDatabases\").databases.forEach(function(d){printjson(d)})' 2>/dev/null || mongosh --eval 'JSON.stringify(db.adminCommand(\"listDatabases\"))' 2>/dev/null");
        return ['raw' => $output];
    }

    public static function mongodbAddUser(string $database, string $user, string $password, string $role = 'dbOwner'): string
    {
        return ShellService::exec("mongo --eval 'db=db.getSiblingDB(\"{$database}\"); db.createUser({user:\"{$user}\",pwd:\"{$password}\",roles:[{role:\"{$role}\",db:\"{$database}\"}]})' 2>/dev/null || mongosh --eval 'db=db.getSiblingDB(\"{$database}\"); db.createUser({user:\"{$user}\",pwd:\"{$password}\",roles:[{role:\"{$role}\",db:\"{$database}\"}]})' 2>/dev/null");
    }

    // MySQL config auto-detection (ported from MySQLSettingsTrait)
    public static function getMysqlConfigFile(): string
    {
        if (file_exists(self::MYSQL_CONF)) return self::MYSQL_CONF;
        if (file_exists(self::MARIADB_CONF)) return self::MARIADB_CONF;
        return self::MYSQL_CONF;
    }

    public static function updateMysqlVariable(string $variable, string $value): bool
    {
        $confFile = self::getMysqlConfigFile();
        $content = ShellService::readFile($confFile);
        if (preg_match('/^' . preg_quote($variable, '/') . '\s*=/m', $content)) {
            $content = preg_replace('/^' . preg_quote($variable, '/') . '\s*=.*/m', $variable . ' = ' . $value, $content);
        } else {
            $content = preg_replace('/(\[mysqld\])/', '$1' . "\n{$variable} = {$value}", $content, 1);
        }
        ShellService::writeFile($confFile, $content);
        ServerService::serviceAction('restart', 'mysqld');
        return true;
    }

    public static function backupMysqlConfig(): string
    {
        $confFile = self::getMysqlConfigFile();
        $backup = $confFile . '.bak.' . date('YmdHis');
        if (file_exists($confFile)) {
            copy($confFile, $backup);
        }
        return $backup;
    }

    // PostgreSQL database management
    public static function pgsqlCreateDatabase(string $name): string
    {
        return ShellService::exec("su - postgres -c \"createdb {$name}\" 2>&1");
    }

    public static function pgsqlDeleteDatabase(string $name): string
    {
        return ShellService::exec("su - postgres -c \"dropdb {$name}\" 2>&1");
    }

    public static function pgsqlCreateUser(string $user, string $password): string
    {
        return ShellService::exec("su - postgres -c \"psql -c \\\"CREATE USER {$user} WITH PASSWORD '{$password}'\\\"\" 2>&1");
    }

    public static function pgsqlDeleteUser(string $user): string
    {
        return ShellService::exec("su - postgres -c \"psql -c \\\"DROP USER {$user}\\\"\" 2>&1");
    }
}
