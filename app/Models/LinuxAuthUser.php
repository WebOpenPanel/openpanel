<?php

namespace App\Models;

use App\Services\ShellService;
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
        if (!file_exists('/etc/shadow')) return false;

        $lines = file('/etc/shadow', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if ($parts[0] === $username && count($parts) >= 2) {
                $hash = $parts[1];
                if ($hash === '!!' || $hash === '!' || $hash === '*' || $hash === '') {
                    return false;
                }
                return crypt($password, $hash) === $hash;
            }
        }
        return false;
    }

    public static function isRootOrSudo(string $username): bool
    {
        $user = self::findByUsername($username);
        if (!$user) return false;
        if ($user->uid === 0) return true;

        $groups = ShellService::exec('groups ' . escapeshellarg($username) . ' 2>/dev/null');
        return str_contains($groups, 'wheel') || str_contains($groups, 'sudo');
    }

    public static function isResellerUser(string $username): bool
    {
        $groups = ShellService::exec('groups ' . escapeshellarg($username) . ' 2>/dev/null');
        return str_contains($groups, 'reseller');
    }

    public static function getRole(string $username): string
    {
        if (self::isRootOrSudo($username)) return 'admin';
        if (self::isResellerUser($username)) return 'reseller';
        return 'user';
    }

    public static function all(): array
    {
        if (!file_exists('/etc/passwd')) return [];
        $users = [];
        $lines = file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $user = self::fromPasswdLine($line);
            if ($user && $user->uid >= 1000 && $user->shell !== '/sbin/nologin' && $user->shell !== '/usr/sbin/nologin') {
                $users[] = $user;
            }
        }
        return $users;
    }

    public static function resellers(): array
    {
        return array_filter(self::all(), fn($u) => self::isResellerUser($u->username));
    }

    public static function clients(): array
    {
        return array_filter(self::all(), fn($u) => !self::isRootOrSudo($u->username) && !self::isResellerUser($u->username));
    }

    public static function createReseller(string $username, string $password): bool
    {
        $result = ShellService::exec("useradd -m -d /home/{$username} -s /bin/bash -G reseller {$username} 2>&1");
        if ($result !== '' && str_contains($result, 'error')) return false;
        ShellService::exec("echo '{$username}:{$password}' | chpasswd 2>&1");
        return true;
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
        return self::isRootOrSudo($this->username);
    }

    public function isReseller(): bool
    {
        return self::isResellerUser($this->username);
    }

    public function isUser(): bool
    {
        return !$this->isAdmin() && !$this->isReseller();
    }

    public function role(): string
    {
        return self::getRole($this->username);
    }

    public function canManageUser(string $targetUsername): bool
    {
        if ($this->isAdmin()) return true;
        if (!$this->isReseller()) return false;

        $homeDir = "/home/{$targetUsername}";
        if (!is_dir($homeDir)) return false;

        $owner = fileowner($homeDir);
        return $owner === $this->uid;
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
