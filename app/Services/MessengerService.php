<?php

namespace App\Services;

class MessengerService
{
    const MESSENGER_DIR = '/usr/local/openpanel/.conf/messenger/';

    public static function getMessages(string $user): array
    {
        $file = self::MESSENGER_DIR . $user . '.json';
        if (!file_exists($file)) return [];
        return json_decode(file_get_contents($file), true) ?? [];
    }

    public static function sendMessage(string $from, string $to, string $message): bool
    {
        $messages = self::getMessages($to);
        $messages[] = [
            'from' => $from,
            'message' => $message,
            'time' => date('Y-m-d H:i:s'),
            'read' => false,
        ];
        if (!is_dir(self::MESSENGER_DIR)) @mkdir(self::MESSENGER_DIR, 0755, true);
        return file_put_contents(self::MESSENGER_DIR . $to . '.json', json_encode($messages, JSON_PRETTY_PRINT)) !== false;
    }

    public static function markRead(string $user): bool
    {
        $messages = self::getMessages($user);
        foreach ($messages as &$msg) {
            $msg['read'] = true;
        }
        return file_put_contents(self::MESSENGER_DIR . $user . '.json', json_encode($messages, JSON_PRETTY_PRINT)) !== false;
    }

    public static function deleteMessage(string $user, int $index): bool
    {
        $messages = self::getMessages($user);
        if (isset($messages[$index])) {
            array_splice($messages, $index, 1);
            file_put_contents(self::MESSENGER_DIR . $user . '.json', json_encode($messages, JSON_PRETTY_PRINT));
        }
        return true;
    }

    public static function getUnreadCount(string $user): int
    {
        $messages = self::getMessages($user);
        return count(array_filter($messages, fn($m) => !($m['read'] ?? true)));
    }

    public static function clearMessages(string $user): bool
    {
        $file = self::MESSENGER_DIR . $user . '.json';
        if (file_exists($file)) @unlink($file);
        return true;
    }
}
