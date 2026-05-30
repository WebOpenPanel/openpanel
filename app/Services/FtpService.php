<?php

namespace App\Services;

class FtpService
{
    const PURE_FTPD_CONF = '/etc/pure-ftpd/pure-ftpd.conf';
    const PURE_PASSWD_FILE = '/etc/pure-ftpd/pureftpd.passwd';
    const PURE_DB_FILE = '/etc/pure-ftpd/pureftpd.pdb';

    public static function getUserList(): array
    {
        $output = ShellService::exec('pure-pw list 2>/dev/null');
        $users = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && !empty($parts[0])) {
                $users[] = [
                    'username' => $parts[0],
                    'path' => $parts[1],
                ];
            }
        }
        return $users;
    }

    public static function getUserInfo(string $username): string
    {
        return ShellService::exec('pure-pw show ' . escapeshellarg($username) . ' 2>&1');
    }

    public static function addUser(string $username, string $password, string $systemUser, string $path): string
    {
        $output = ShellService::exec("(echo " . escapeshellarg($password) . "; echo " . escapeshellarg($password) . ") | pure-pw useradd " . escapeshellarg($username) . " -u " . escapeshellarg($systemUser) . " -g " . escapeshellarg($systemUser) . " -d " . escapeshellarg($path) . " -m 2>&1");
        return $output;
    }

    public static function deleteUser(string $username): string
    {
        return ShellService::exec('pure-pw userdel ' . escapeshellarg($username) . ' -m 2>&1');
    }

    public static function changePassword(string $username, string $password): string
    {
        return ShellService::exec("(echo " . escapeshellarg($password) . "; echo " . escapeshellarg($password) . ") | pure-pw passwd " . escapeshellarg($username) . " -m 2>&1");
    }

    public static function updateUserPath(string $username, string $newPath): string
    {
        return ShellService::exec('pure-pw usermod ' . escapeshellarg($username) . ' -d ' . escapeshellarg($newPath) . ' -m 2>&1');
    }

    public static function getActiveSessions(): string
    {
        return ShellService::exec('pure-ftpwho -W -H 2>/dev/null');
    }

    public static function killSession(int $pid): string
    {
        return ShellService::exec('kill -9 ' . $pid . ' 2>&1');
    }

    public static function getConf(): string
    {
        return ShellService::readFile(self::PURE_FTPD_CONF);
    }

    public static function saveConf(string $content): bool
    {
        ShellService::writeFile(self::PURE_FTPD_CONF, $content);
        ServerService::serviceAction('restart', 'pure-ftpd');
        return true;
    }

    public static function getStatus(): array
    {
        $status = ShellService::exec('systemctl is-active pure-ftpd 2>/dev/null');
        return ['active' => trim($status) === 'active'];
    }

    public static function restart(): string
    {
        return ServerService::serviceAction('restart', 'pure-ftpd');
    }

    public static function generateDb(): string
    {
        return ShellService::exec('pure-pw mkdb 2>&1');
    }

    public static function getUserCount(): int
    {
        $output = ShellService::exec('pure-pw list 2>/dev/null | wc -l');
        return (int) $output;
    }

    public static function suspendUser(string $username): bool
    {
        $userInfo = self::getUserInfo($username);
        if (empty($userInfo)) return false;
        ShellService::exec("pure-pw usermod " . escapeshellarg($username) . " -r '' -m 2>&1");
        return true;
    }

    public static function unsuspendUser(string $username): bool
    {
        ShellService::exec("pure-pw usermod " . escapeshellarg($username) . " -r '*' -m 2>&1");
        return true;
    }
}
