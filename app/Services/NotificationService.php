<?php

namespace App\Services;

class NotificationService
{
    const NOTIFICATION_DB = '/usr/local/openpanel/.conf/notifications.sqlite';

    public static function getNotifications(int $limit = 50): array
    {
        $db = self::openDb();
        if (!$db) return [];
        $results = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT " . (int) $limit);
        $notifications = [];
        while ($row = $results->fetchArray(\SQLITE3_ASSOC)) {
            $notifications[] = $row;
        }
        $db->close();
        return $notifications;
    }

    public static function addNotification(string $type, string $message, string $level = 'info'): bool
    {
        $db = self::openDb();
        if (!$db) return false;
        $stmt = $db->prepare("INSERT INTO notifications (type, message, level, created_at, read_status) VALUES (:type, :message, :level, :created_at, 0)");
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':message', $message);
        $stmt->bindValue(':level', $level);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'));
        $result = $stmt->execute();
        $db->close();
        return $result !== false;
    }

    public static function markAsRead(int $id): bool
    {
        $db = self::openDb();
        if (!$db) return false;
        $result = $db->exec("UPDATE notifications SET read_status=1 WHERE id=" . (int) $id);
        $db->close();
        return $result !== false;
    }

    public static function markAllRead(): bool
    {
        $db = self::openDb();
        if (!$db) return false;
        $result = $db->exec("UPDATE notifications SET read_status=1");
        $db->close();
        return $result !== false;
    }

    public static function deleteNotification(int $id): bool
    {
        $db = self::openDb();
        if (!$db) return false;
        $result = $db->exec("DELETE FROM notifications WHERE id=" . (int) $id);
        $db->close();
        return $result !== false;
    }

    public static function getUnreadCount(): int
    {
        $db = self::openDb();
        if (!$db) return 0;
        $result = $db->querySingle("SELECT COUNT(*) FROM notifications WHERE read_status=0");
        $db->close();
        return (int) $result;
    }

    public static function clearAll(): bool
    {
        $db = self::openDb();
        if (!$db) return false;
        $result = $db->exec("DELETE FROM notifications");
        $db->close();
        return $result !== false;
    }

    private static function openDb(): ?\SQLite3
    {
        if (!class_exists('SQLite3')) return null;
        $db = new \SQLite3(self::NOTIFICATION_DB, \SQLITE3_OPEN_CREATE | \SQLITE3_OPEN_READWRITE);
        $db->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT,
            message TEXT,
            level TEXT DEFAULT 'info',
            created_at TEXT,
            read_status INTEGER DEFAULT 0
        )");
        return $db;
    }
}
