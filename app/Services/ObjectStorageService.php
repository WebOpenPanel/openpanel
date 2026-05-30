<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ObjectStorageService
{
    protected static string $configPath = '/usr/local/openpanel/.conf/object_storage.json';
    protected static string $s3cmdConfig = '/root/.s3cfg';
    protected static string $rcloneConfig = '/root/.config/rclone/rclone.conf';

    public static function getConfig(): array
    {
        if (!file_exists(self::$configPath)) {
            return self::getDefaultConfig();
        }
        return json_decode(file_get_contents(self::$configPath), true) ?: self::getDefaultConfig();
    }

    public static function saveConfig(array $data): array
    {
        File::ensureDirectoryExists(dirname(self::$configPath));
        file_put_contents(self::$configPath, json_encode($data, JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'Object storage configuration saved.'];
    }

    public static function testConnection(array $config): array
    {
        $provider = $config['provider'] ?? 's3';

        if ($provider === 's3' || $provider === 'minio') {
            return self::testS3Connection($config);
        }

        return ['success' => false, 'message' => 'Unsupported provider.'];
    }

    public static function listBuckets(array $config): array
    {
        $provider = $config['provider'] ?? 's3';

        if ($provider === 's3' || $provider === 'minio') {
            return self::listS3Buckets($config);
        }

        return ['success' => false, 'message' => 'Unsupported provider.'];
    }

    public static function generateS3cmdConfig(array $config): string
    {
        $host = $config['endpoint'] ?? 's3.amazonaws.com';
        $host = preg_replace('#^https?://#', '', $host);

        $ini = "[default]\n";
        $ini .= "access_key = {$config['access_key']}\n";
        $ini .= "secret_key = {$config['secret_key']}\n";
        $ini .= "host_base = {$host}\n";
        $ini .= "host_bucket = %%(bucket)s.{$host}\n";
        $ini .= "use_https = " . (($config['use_ssl'] ?? true) ? 'True' : 'False') . "\n";
        $ini .= "signature_v2 = False\n";

        return $ini;
    }

    public static function saveS3cmdConfig(array $config): array
    {
        $ini = self::generateS3cmdConfig($config);
        File::ensureDirectoryExists(dirname(self::$s3cmdConfig));
        file_put_contents(self::$s3cmdConfig, $ini);
        return ['success' => true, 'message' => 's3cmd configuration saved.'];
    }

    public static function generateRcloneConfig(array $config): string
    {
        $provider = $config['provider'] ?? 's3';
        $ini = "[openpanel-s3]\n";
        $ini .= "type = s3\n";
        $ini .= "provider = " . ($provider === 'minio' ? 'Minio' : 'AWS') . "\n";
        $ini .= "access_key_id = {$config['access_key']}\n";
        $ini .= "secret_access_key = {$config['secret_key']}\n";
        $ini .= "endpoint = " . ($config['endpoint'] ?? 's3.amazonaws.com') . "\n";
        $ini .= "region = " . ($config['region'] ?? 'us-east-1') . "\n";

        return $ini;
    }

    public static function saveRcloneConfig(array $config): array
    {
        File::ensureDirectoryExists(dirname(self::$rcloneConfig));
        $content = file_exists(self::$rcloneConfig) ? file_get_contents(self::$rcloneConfig) : '';
        $newConfig = self::generateRcloneConfig($config);
        $content = preg_replace('/\[openpanel-s3\].*?(?=\[|\z)/s', $newConfig, $content);
        if (stripos($content, '[openpanel-s3]') === false) {
            $content .= "\n{$newConfig}";
        }
        file_put_contents(self::$rcloneConfig, $content);
        return ['success' => true, 'message' => 'rclone configuration saved.'];
    }

    protected static function getDefaultConfig(): array
    {
        return [
            'provider' => 's3',
            'endpoint' => '',
            'access_key' => '',
            'secret_key' => '',
            'region' => 'us-east-1',
            'use_ssl' => true,
            'bucket' => '',
            'auto_backup' => false,
            'backup_schedule' => 'daily',
        ];
    }

    protected static function testS3Connection(array $config): array
    {
        self::saveS3cmdConfig($config);
        $bucket = $config['bucket'] ?? '';
        if ($bucket) {
            $output = ShellService::exec("s3cmd ls s3://{$bucket} 2>&1");
        } else {
            $output = ShellService::exec('s3cmd ls 2>&1');
        }
        $success = stripos($output, 'error') === false && stripos($output, 'failed') === false && stripos($output, 'access denied') === false;
        return ['success' => $success, 'output' => $output];
    }

    protected static function listS3Buckets(array $config): array
    {
        self::saveS3cmdConfig($config);
        $output = ShellService::exec('s3cmd ls 2>&1');
        $buckets = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+(.+)$/', trim($line), $m)) {
                $buckets[] = trim($m[1]);
            }
        }
        return ['success' => true, 'buckets' => $buckets];
    }
}
