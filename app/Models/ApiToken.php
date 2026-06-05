<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $fillable = [
        'name', 'token', 'scopes', 'allowed_ips',
        'reseller_username', 'last_used_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_ips' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['token'];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function hasScope(string $scope): bool
    {
        if (!$this->scopes || in_array('*', $this->scopes) || in_array('admin:all', $this->scopes)) {
            return true;
        }
        return in_array($scope, $this->scopes);
    }

    public function isIpAllowed(string $ip): bool
    {
        if (!$this->allowed_ips || empty($this->allowed_ips)) {
            return true;
        }
        return in_array($ip, $this->allowed_ips);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isReseller(): bool
    {
        return !empty($this->reseller_username);
    }

    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function requestLogs()
    {
        return $this->hasMany(ApiRequestLog::class, 'token_id');
    }

    public static function availableScopes(): array
    {
        return [
            'admin:all',
            'accounts:create',
            'accounts:read',
            'accounts:suspend',
            'accounts:unsuspend',
            'accounts:terminate',
            'accounts:update',
            'wordpress:manage',
            'dns:manage',
            'email:manage',
            'database:manage',
            'ssl:manage',
            'varnish:manage',
            'reseller:manage',
        ];
    }
}
