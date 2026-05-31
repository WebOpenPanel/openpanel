<?php

namespace App\Auth;

use App\Models\LinuxAuthUser;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class LinuxUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return LinuxAuthUser::findByUsername($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $username = $credentials['username'] ?? $credentials['email'] ?? '';
        return LinuxAuthUser::findByUsername($username);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? '';
        return LinuxAuthUser::verifyPassword($user->getAuthIdentifier(), $password);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): ?string
    {
        return null;
    }
}
