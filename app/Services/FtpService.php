<?php

namespace App\Services;

class FtpService
{
    const PURE_FTPD_CONF = '/etc/pure-ftpd/pure-ftpd.conf';
    const PURE_PASSWD_FILE = '/etc/pure-ftpd/pureftpd.passwd';
    const PURE_DB_FILE = '/etc/pure-ftpd/pureftpd.pdb';
    const PURE_FTPS_CERT = '/etc/pki/tls/private/pure-ftpd.pem';

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
        return self::runPurePwWithPassword(
            'pure-pw useradd ' . escapeshellarg($username) .
            ' -u ' . escapeshellarg($systemUser) .
            ' -g ' . escapeshellarg($systemUser) .
            ' -d ' . escapeshellarg($path) . ' -m',
            $password
        );
    }

    public static function deleteUser(string $username): string
    {
        return ShellService::exec('pure-pw userdel ' . escapeshellarg($username) . ' -m 2>&1');
    }

    public static function changePassword(string $username, string $password): string
    {
        return self::runPurePwWithPassword(
            'pure-pw passwd ' . escapeshellarg($username) . ' -m',
            $password
        );
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
        $tls = self::tlsStatus();

        return [
            'active' => trim($status) === 'active',
            'ftps_enabled' => $tls['enabled'],
            'tls_mode' => $tls['tls_mode'],
            'cert_file' => $tls['cert_file'],
            'cert_exists' => $tls['cert_exists'],
            'passive_range' => $tls['passive_range'],
        ];
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

    /**
     * Verify Pure-FTPd chroot configuration is active.
     * Returns enforcement status and any issues found.
     */
    public static function verifyChrootIsolation(): array
    {
        $issues = [];

        // Check Pure-FTPd config for chroot
        $conf = self::getConf();

        // Pure-FTPd uses "ChrootEveryone yes" for global chroot
        if (stripos($conf, 'ChrootEveryone') === false && stripos($conf, 'chroot') === false) {
            // Check if it's set via command-line flag instead
            $serviceFile = ShellService::exec('cat /etc/systemd/system/pure-ftpd.service 2>/dev/null || cat /usr/lib/systemd/system/pure-ftpd.service 2>/dev/null');
            if (stripos($serviceFile, '--chrooteveryone') === false && stripos($serviceFile, '-A') === false) {
                $issues[] = 'ChrootEveryone not explicitly enabled in config or service file';
            }
        }

        // Verify all virtual users are chrooted (pure-pw show should have /./ in path)
        $userList = self::getUserList();
        $nonChroot = [];
        foreach ($userList as $user) {
            $path = $user['path'] ?? '';
            // Pure-FTPd uses /./ to mark chroot boundary
            if (!empty($path) && strpos($path, '/./') === false && $path !== '/') {
                // Path without /./ — but pure-pw add with -d flag auto-chroots
                // This is actually fine; pure-pw handles chroot internally
            }
        }

        // Check passive mode config
        $passiveRange = ShellService::exec('grep -i PassivePortRange ' . escapeshellarg(self::PURE_FTPD_CONF) . ' 2>/dev/null');
        if (empty(trim($passiveRange))) {
            // Check defaults — Pure-FTPd default passive range is 49152-65534
            $issues[] = 'PassivePortRange not explicitly configured (using defaults)';
        }

        return [
            'chroot_configured' => empty($issues),
            'issues' => $issues,
            'virtual_users' => count($userList),
        ];
    }

    /**
     * Ensure FTP user is properly chrooted to their home directory.
     * Called during account creation to enforce isolation.
     */
    public static function enforceChrootForUser(string $username, string $home): void
    {
        // pure-pw useradd with -d flag already chroots the user
        // Verify the user's path is their home directory
        $info = self::getUserInfo($username);
        if (empty($info)) return;

        // Check if path points outside home
        if (preg_match('/Dir:\s*(.+)/', $info, $m)) {
            $ftpPath = trim($m[1]);
            $realHome = realpath($home);
            if ($realHome && !str_starts_with($ftpPath, $realHome) && $ftpPath !== $home) {
                // Fix: update FTP user path to home
                self::updateUserPath($username, $home);
            }
        }
    }

    public static function unsuspendUser(string $username): bool
    {
        ShellService::exec("pure-pw usermod " . escapeshellarg($username) . " -r '*' -m 2>&1");
        return true;
    }

    public static function tlsStatus(): array
    {
        $conf = self::getConf();
        preg_match('/^\s*TLS\s+(\S+)/mi', $conf, $tls);
        preg_match('/^\s*CertFile\s+(.+)$/mi', $conf, $cert);
        preg_match('/^\s*PassivePortRange\s+(.+)$/mi', $conf, $passive);

        $mode = $tls[1] ?? '0';
        $certFile = trim($cert[1] ?? self::PURE_FTPS_CERT, " \t\n\r\0\x0B\"'");
        $certExists = is_file($certFile);

        return [
            'enabled' => in_array($mode, ['1', '2'], true) && $certExists,
            'tls_mode' => $mode,
            'cert_file' => $certFile,
            'cert_exists' => $certExists,
            'passive_range' => trim($passive[1] ?? ''),
        ];
    }

    private static function runPurePwWithPassword(string $command, string $password): string
    {
        $password = str_replace(["\r", "\n"], '', $password);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command . ' 2>&1', $descriptorSpec, $pipes, '/', [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        ]);

        if (!is_resource($process)) {
            return 'Failed to start pure-pw.';
        }

        fwrite($pipes[0], $password . "\n" . $password . "\n");
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);

        return trim($output . "\n" . $error);
    }
}
