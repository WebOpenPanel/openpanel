<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ClusterService
{
    const CLUSTER_DB = 'root_cluster';
    const CLUSTER_LOG = '/var/log/openpanel/cluster.log';

    public static function isConfigured(): bool
    {
        return file_exists('/usr/local/openpanel/.conf/cluster.conf');
    }

    public static function initCluster(): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS " . self::CLUSTER_DB);
        DB::connection('mysql')->statement("USE " . self::CLUSTER_DB);
        DB::statement("CREATE TABLE IF NOT EXISTS servers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            ip VARCHAR(45),
            apikey VARCHAR(255),
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        DB::statement("CREATE TABLE IF NOT EXISTS pending_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            server_id INT,
            action VARCHAR(255),
            data TEXT,
            answer TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        DB::statement("CREATE TABLE IF NOT EXISTS config (
            name VARCHAR(255) PRIMARY KEY,
            value TEXT
        )");
        ShellService::exec('touch /usr/local/openpanel/.conf/cluster.conf');
    }

    public static function getServers(): array
    {
        try {
            return DB::connection('mysql')->table(self::CLUSTER_DB . '.servers')->get()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function addServer(string $name, string $ip, string $apikey): bool
    {
        try {
            DB::connection('mysql')->table(self::CLUSTER_DB . '.servers')->insert([
                'name' => $name, 'ip' => $ip, 'apikey' => $apikey, 'status' => 'active',
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function removeServer(int $id): bool
    {
        try {
            DB::connection('mysql')->table(self::CLUSTER_DB . '.servers')->where('id', $id)->delete();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function sendApi(object $server, string $action, array $data): string
    {
        $postData = array_merge(['key' => $server->apikey, 'action' => $action], $data);
        $url = "https://{$server->ip}:2304/v1/cluster/";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => http_build_query($postData),
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $response = json_encode(['status' => 'Error', 'msj' => curl_error($ch)]);
        }
        curl_close($ch);
        self::log("API {$action} to {$server->ip}: " . substr($response, 0, 200));
        return $response;
    }

    public static function getConfig(): array
    {
        try {
            $rows = DB::connection('mysql')->table(self::CLUSTER_DB . '.config')->get();
            $config = [];
            foreach ($rows as $row) $config[$row->name] = $row->value;
            return $config;
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function setConfig(string $name, string $value): void
    {
        try {
            DB::connection('mysql')->table(self::CLUSTER_DB . '.config')->upsert(['name' => $name, 'value' => $value], ['name']);
        } catch (\Exception $e) {}
    }

    public static function getPendingTransactions(): array
    {
        try {
            return DB::connection('mysql')->table(self::CLUSTER_DB . '.pending_transactions')->where('status', 'pending')->get()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function log(string $msg): void
    {
        $config = self::getConfig();
        if (($config['activeLog'] ?? 0) == 1) {
            $date = date('Y-m-d H:i:s');
            ShellService::exec("echo '{$date} {$msg}' >> " . self::CLUSTER_LOG);
        }
    }
}
