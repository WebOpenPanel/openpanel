<?php

namespace App\Services;

class HelpDeskService
{
    const HELPDESK_PATH = '/usr/local/openpanel/include/helpdesk/';

    public static function isInstalled(): bool
    {
        return is_dir(self::HELPDESK_PATH);
    }

    public static function install(): bool
    {
        if (!is_dir(self::HELPDESK_PATH)) @mkdir(self::HELPDESK_PATH, 0755, true);
        return true;
    }

    public static function getConfig(): array
    {
        $configFile = self::HELPDESK_PATH . 'config.json';
        if (!file_exists($configFile)) return [];
        return json_decode(file_get_contents($configFile), true) ?? [];
    }

    public static function saveConfig(array $config): bool
    {
        if (!is_dir(self::HELPDESK_PATH)) @mkdir(self::HELPDESK_PATH, 0755, true);
        return file_put_contents(self::HELPDESK_PATH . 'config.json', json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }

    public static function getTickets(): array
    {
        $config = self::getConfig();
        return $config['tickets'] ?? [];
    }

    public static function addTicket(array $ticket): bool
    {
        $config = self::getConfig();
        $config['tickets'] = $config['tickets'] ?? [];
        $ticket['id'] = count($config['tickets']) + 1;
        $ticket['created_at'] = date('Y-m-d H:i:s');
        $ticket['status'] = 'open';
        $config['tickets'][] = $ticket;
        return self::saveConfig($config);
    }

    public static function updateTicket(int $id, array $data): bool
    {
        $config = self::getConfig();
        foreach ($config['tickets'] ?? [] as &$ticket) {
            if (($ticket['id'] ?? 0) === $id) {
                $ticket = array_merge($ticket, $data);
                break;
            }
        }
        return self::saveConfig($config);
    }

    public static function deleteTicket(int $id): bool
    {
        $config = self::getConfig();
        $config['tickets'] = array_filter($config['tickets'] ?? [], fn($t) => ($t['id'] ?? 0) !== $id);
        return self::saveConfig($config);
    }
}
