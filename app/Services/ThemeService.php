<?php

namespace App\Services;

class ThemeService
{
    const THEMES_DB = '/usr/local/openpanel/.conf/openpanel_themes.sqlite';
    const LANG_DIR = '/usr/local/openpanel/include/lang/';
    const THEME_DIR = '/usr/local/openpanel/htdocs/admin/design/';

    public static function getCurrentTheme(): string
    {
        $db = self::openDb();
        if (!$db) return 'default';
        $result = $db->querySingle("SELECT value FROM config WHERE key='theme'");
        $db->close();
        return $result ?: 'default';
    }

    public static function setTheme(string $theme): bool
    {
        $db = self::openDb();
        if (!$db) return false;
        $db->exec("INSERT OR REPLACE INTO config (key, value) VALUES ('theme', '" . $db->escapeString($theme) . "')");
        $db->close();
        return true;
    }

    public static function listThemes(): array
    {
        if (!is_dir(self::THEME_DIR)) return ['default'];
        $themes = [];
        foreach (ShellService::dirList(self::THEME_DIR) as $dir) {
            if (is_dir(self::THEME_DIR . $dir) && $dir !== '.' && $dir !== '..') {
                $themes[] = $dir;
            }
        }
        return $themes;
    }

    public static function getCurrentLanguage(): string
    {
        $db = self::openDb();
        if (!$db) return 'en';
        $result = $db->querySingle("SELECT value FROM config WHERE key='language'");
        $db->close();
        return $result ?: 'en';
    }

    public static function setLanguage(string $lang): bool
    {
        $db = self::openDb();
        if (!$db) return false;
        $db->exec("INSERT OR REPLACE INTO config (key, value) VALUES ('language', '" . $db->escapeString($lang) . "')");
        $db->close();
        return true;
    }

    public static function listLanguages(): array
    {
        if (!is_dir(self::LANG_DIR)) return ['en'];
        $languages = [];
        foreach (ShellService::dirList(self::LANG_DIR) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $languages[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }
        return $languages;
    }

    public static function getLanguageContent(string $lang): string
    {
        $file = self::LANG_DIR . $lang . '.php';
        return file_exists($file) ? ShellService::readFile($file) : '';
    }

    public static function saveLanguageContent(string $lang, string $content): bool
    {
        return ShellService::writeFile(self::LANG_DIR . $lang . '.php', $content);
    }

    private static function openDb(): ?\SQLite3
    {
        if (!class_exists('SQLite3')) return null;
        $db = new \SQLite3(self::THEMES_DB, \SQLITE3_OPEN_CREATE | \SQLITE3_OPEN_READWRITE);
        $db->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT)");
        return $db;
    }
}
