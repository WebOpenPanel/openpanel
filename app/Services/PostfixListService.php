<?php

namespace App\Services;

class PostfixListService
{
    protected static array $listTypes = ['access', 'body_checks', 'client_checks', 'helo_checks', 'mynetworks', 'recipient_checks', 'relay_domains', 'sender_checks', 'transport'];

    public static function getLists(): array
    {
        $lists = [];
        foreach (self::$listTypes as $type) {
            $path = self::getListPath($type);
            $lists[] = [
                'name' => $type,
                'path' => $path,
                'exists' => file_exists($path),
                'entries' => file_exists($path) ? count(array_filter(explode("\n", file_get_contents($path)))) : 0,
            ];
        }
        return $lists;
    }

    public static function getList(string $type): array
    {
        $path = self::getListPath($type);
        if (!file_exists($path)) {
            return [];
        }
        $entries = [];
        foreach (explode("\n", file_get_contents($path)) as $line) {
            $line = trim($line);
            if ($line && !str_starts_with($line, '#')) {
                $parts = preg_split('/\s+/', $line, 2);
                $entries[] = [
                    'pattern' => $parts[0],
                    'action' => $parts[1] ?? 'OK',
                ];
            }
        }
        return $entries;
    }

    public static function addEntry(string $type, string $pattern, string $action = 'OK'): array
    {
        $path = self::getListPath($type);
        $sanitizedPattern = escapeshellarg($pattern);
        $content = file_exists($path) ? file_get_contents($path) : "# OpenPanel {$type} list\n";

        if (stripos($content, $pattern) !== false) {
            return ['success' => false, 'message' => 'Entry already exists.'];
        }

        $content .= "{$pattern}\t{$action}\n";
        file_put_contents($path, $content);
        self::postmap($type);

        return ['success' => true, 'message' => "Entry added to {$type}."];
    }

    public static function removeEntry(string $type, string $pattern): array
    {
        $path = self::getListPath($type);
        if (!file_exists($path)) {
            return ['success' => false, 'message' => 'List not found.'];
        }

        $lines = explode("\n", file_get_contents($path));
        $lines = array_filter($lines, function ($line) use ($pattern) {
            return trim($line) === '' || str_starts_with(trim($line), '#') || strpos($line, $pattern) === false;
        });
        file_put_contents($path, implode("\n", $lines) . "\n");
        self::postmap($type);

        return ['success' => true, 'message' => "Entry removed from {$type}."];
    }

    public static function updateEntry(string $type, string $oldPattern, string $newPattern, string $action): array
    {
        self::removeEntry($type, $oldPattern);
        return self::addEntry($type, $newPattern, $action);
    }

    public static function getAvailableActions(): array
    {
        return ['OK', 'REJECT', 'DISCARD', 'HOLD', 'DEFER', 'FILTER', 'REDIRECT'];
    }

    protected static function getListPath(string $type): string
    {
        $sanitized = preg_replace('/[^a-z_]/', '', strtolower($type));
        return "/etc/postfix/{$sanitized}";
    }

    protected static function postmap(string $type): void
    {
        $path = self::getListPath($type);
        if (file_exists($path)) {
            ShellService::exec("postmap {$path} 2>/dev/null");
            ShellService::exec('systemctl reload postfix 2>/dev/null');
        }
    }
}
