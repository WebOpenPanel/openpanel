<?php

namespace App\Services;

class HooksService
{
    const HOOKS_DIR = '/usr/local/openpanel/.conf/hooks/';
    const HOOKS_FILE = '/usr/local/openpanel/.conf/hooks.json';

    public static function getHooks(): array
    {
        if (!file_exists(self::HOOKS_FILE)) return [];
        return json_decode(file_get_contents(self::HOOKS_FILE), true) ?? [];
    }

    public static function addHook(string $event, string $command, string $description = ''): bool
    {
        $hooks = self::getHooks();
        $hooks[] = [
            'event' => $event,
            'command' => $command,
            'description' => $description,
            'enabled' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return file_put_contents(self::HOOKS_FILE, json_encode($hooks, JSON_PRETTY_PRINT)) !== false;
    }

    public static function removeHook(int $index): bool
    {
        $hooks = self::getHooks();
        if (isset($hooks[$index])) {
            array_splice($hooks, $index, 1);
            return file_put_contents(self::HOOKS_FILE, json_encode($hooks, JSON_PRETTY_PRINT)) !== false;
        }
        return false;
    }

    public static function toggleHook(int $index): bool
    {
        $hooks = self::getHooks();
        if (isset($hooks[$index])) {
            $hooks[$index]['enabled'] = !($hooks[$index]['enabled'] ?? true);
            return file_put_contents(self::HOOKS_FILE, json_encode($hooks, JSON_PRETTY_PRINT)) !== false;
        }
        return false;
    }

    public static function executeHooks(string $event): array
    {
        $hooks = self::getHooks();
        $results = [];
        foreach ($hooks as $hook) {
            if (($hook['event'] ?? '') === $event && ($hook['enabled'] ?? true)) {
                $results[] = ['command' => $hook['command'], 'output' => ShellService::exec($hook['command'] . ' 2>&1')];
            }
        }
        return $results;
    }

    public static function listEvents(): array
    {
        return [
            'pre_account_create', 'post_account_create',
            'pre_account_delete', 'post_account_delete',
            'pre_account_suspend', 'post_account_suspend',
            'pre_account_unsuspend', 'post_account_unsuspend',
            'pre_dns_create', 'post_dns_create',
            'pre_dns_delete', 'post_dns_delete',
            'pre_ssl_install', 'post_ssl_install',
            'pre_backup', 'post_backup',
            'pre_restore', 'post_restore',
        ];
    }
}
