<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TomcatService
{
    const TOMCAT_PATH = '/usr/local/tomcat';
    const USERS_CONF = '/usr/local/tomcat/conf/tomcat-users.xml';

    public static function isInstalled(): bool
    {
        return is_dir(self::TOMCAT_PATH);
    }

    public static function install(): string
    {
        return ShellService::exec('/usr/local/openpanel/include/tomcat_install.php 2>&1');
    }

    public static function uninstall(): string
    {
        ServerService::serviceAction('stop', 'tomcat');
        return ShellService::exec('rm -rf /usr/local/tomcat 2>&1');
    }

    public static function status(): array
    {
        $running = trim(ShellService::exec("ps aux | grep '[c]atalina' | wc -l"));
        return ['running' => (int) $running > 0, 'pid' => trim(ShellService::exec("pgrep -f catalina 2>/dev/null"))];
    }

    public static function start(): string { return ServerService::serviceAction('start', 'tomcat'); }
    public static function stop(): string { return ServerService::serviceAction('stop', 'tomcat'); }
    public static function restart(): string { return ServerService::serviceAction('restart', 'tomcat'); }

    public static function getUsers(): array
    {
        if (!file_exists(self::USERS_CONF)) return [];
        $content = ShellService::readFile(self::USERS_CONF);
        $users = [];
        if (preg_match_all('/user\s+username="([^"]+)"\s+password="([^"]+)"\s+roles="([^"]+)"/', $content, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $users[] = ['username' => $match[1], 'roles' => $match[3]];
            }
        }
        return $users;
    }

    public static function addUser(string $username, string $password, string $roles = 'manager-gui'): bool
    {
        $content = ShellService::readFile(self::USERS_CONF);
        $userLine = '  <user username="' . $username . '" password="' . $password . '" roles="' . $roles . '"/>';
        $content = str_replace('</tomcat-users>', $userLine . "\n</tomcat-users>", $content);
        ShellService::writeFile(self::USERS_CONF, $content);
        ServerService::serviceAction('restart', 'tomcat');
        return true;
    }

    public static function deleteUser(string $username): bool
    {
        $content = ShellService::readFile(self::USERS_CONF);
        $content = preg_replace('/\s*<user\s+username="' . preg_quote($username, '/') . '"[^>]*\/>\s*/', "\n", $content);
        ShellService::writeFile(self::USERS_CONF, $content);
        ServerService::serviceAction('restart', 'tomcat');
        return true;
    }

    public static function deployWar(string $warPath, ?string $context = null): string
    {
        $dest = self::TOMCAT_PATH . '/webapps/' . ($context ?: pathinfo($warPath, PATHINFO_BASENAME));
        return ShellService::exec('cp ' . escapeshellarg($warPath) . ' ' . escapeshellarg($dest) . ' 2>&1');
    }

    public static function undeploy(string $appName): string
    {
        $path = self::TOMCAT_PATH . '/webapps/' . $appName;
        if (is_dir($path)) ShellService::exec('rm -rf ' . escapeshellarg($path));
        $war = $path . '.war';
        if (file_exists($war)) @unlink($war);
        return 'Undeployed ' . $appName;
    }

    public static function getApps(): array
    {
        $webapps = self::TOMCAT_PATH . '/webapps';
        if (!is_dir($webapps)) return [];
        $apps = [];
        foreach (ShellService::dirList($webapps) as $item) {
            if ($item !== 'ROOT' && $item !== 'manager' && $item !== 'host-manager') {
                $apps[] = ['name' => $item, 'path' => $webapps . '/' . $item, 'is_dir' => is_dir($webapps . '/' . $item)];
            }
        }
        return $apps;
    }

    public static function getServerXml(): string
    {
        return ShellService::readFile(self::TOMCAT_PATH . '/conf/server.xml');
    }

    public static function saveServerXml(string $content): bool
    {
        ShellService::writeFile(self::TOMCAT_PATH . '/conf/server.xml', $content);
        ServerService::serviceAction('restart', 'tomcat');
        return true;
    }
}
