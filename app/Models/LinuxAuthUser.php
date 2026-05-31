<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;

class LinuxAuthUser implements Authenticatable
{
    protected string $username;
    protected int $uid;
    protected int $gid;
    protected string $gecos;
    protected string $homeDir;
    protected string $shell;

    public function __construct(string $username, int $uid, int $gid, string $gecos, string $homeDir, string $shell)
    {
        $this->username = $username;
        $this->uid = $uid;
        $this->gid = $gid;
        $this->gecos = $gecos;
        $this->homeDir = $homeDir;
        $this->shell = $shell;
    }

    public static function fromPasswdLine(string $line): ?self
    {
        $parts = explode(':', $line);
        if (count($parts) < 7) return null;

        return new self(
            username: $parts[0],
            uid: (int) $parts[2],
            gid: (int) $parts[3],
            gecos: $parts[4],
            homeDir: $parts[5],
            shell: $parts[6],
        );
    }

    public static function findByUsername(string $username): ?self
    {
        if (!file_exists('/etc/passwd')) return null;

        $lines = file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with($line, $username . ':')) {
                return self::fromPasswdLine($line);
            }
        }
        return null;
    }

    public static function verifyPassword(string $username, string $password): bool
    {
        $helper = '/usr/local/openpanel/bin/auth-check';
        if (!file_exists($helper)) {
            return false;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [$helper, $username, $password],
            $descriptors,
            $pipes,
            '/'
        );

        if (!is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        return $exitCode === 0;
    }

    public static function isRootOrSudo(string $username): bool
    {
        $user = self::findByUsername($username);
        if (!$user) return false;
        if ($user->uid === 0) return true;

        $groups = shell_exec("groups {$username} 2>/dev/null") ?: '';
        return str_contains($groups, 'wheel') || str_contains($groups, 'sudo');
    }

    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function getAuthIdentifier(): string
    {
        return $this->username;
    }

    public function getAuthPassword(): string
    {
        return 'verified-via-helper';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getHomeDir(): string
    {
        return $this->homeDir;
    }

    public function getShell(): string
    {
        return $this->shell;
    }

    public function isAdmin(): bool
    {
        return $this->uid === 0 || self::isRootOrSudo($this->username);
    }

    public function role(): string
    {
        return $this->isAdmin() ? 'admin' : 'user';
    }

    public function __get(string $key): mixed
    {
        return match ($key) {
            'username' => $this->username,
            'uid' => $this->uid,
            'gid' => $this->gid,
            'email' => $this->username . '@' . gethostname(),
            'home' => $this->homeDir,
            'homeDir' => $this->homeDir,
            'shell' => $this->shell,
            'role' => $this->role(),
            'status' => 'active',
            default => null,
        };
    }
}
