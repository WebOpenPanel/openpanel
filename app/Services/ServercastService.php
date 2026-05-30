<?php

namespace App\Services;

class ServercastService
{
    const CAST_DIR = '/usr/local/openpanel/.conf/servercast/';

    public static function isInstalled(): bool
    {
        return is_dir(self::CAST_DIR);
    }

    public static function install(): bool
    {
        if (!is_dir(self::CAST_DIR)) @mkdir(self::CAST_DIR, 0755, true);
        return true;
    }

    public static function listCasts(): array
    {
        if (!is_dir(self::CAST_DIR)) return [];
        $casts = [];
        foreach (ShellService::dirList(self::CAST_DIR) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $data = json_decode(file_get_contents(self::CAST_DIR . $file), true);
                if ($data) {
                    $data['file'] = $file;
                    $casts[] = $data;
                }
            }
        }
        return $casts;
    }

    public static function addCast(array $data): bool
    {
        if (!is_dir(self::CAST_DIR)) @mkdir(self::CAST_DIR, 0755, true);
        $filename = 'cast_' . time() . '.json';
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['status'] = 'pending';
        return file_put_contents(self::CAST_DIR . $filename, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    public static function deleteCast(string $file): bool
    {
        $path = self::CAST_DIR . $file;
        if (file_exists($path)) @unlink($path);
        return true;
    }

    public static function executeCast(string $file): string
    {
        $path = self::CAST_DIR . $file;
        if (!file_exists($path)) return 'Cast not found';
        $data = json_decode(file_get_contents($path), true);
        if (!$data) return 'Invalid cast data';
        $command = $data['command'] ?? '';
        if (empty($command)) return 'No command defined';
        $output = ShellService::exec($command . ' 2>&1');
        $data['status'] = 'executed';
        $data['executed_at'] = date('Y-m-d H:i:s');
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $output;
    }
}
