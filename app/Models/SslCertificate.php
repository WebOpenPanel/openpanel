<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SslCertificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_account_id', 'domain', 'certificate', 'private_key',
        'ca_bundle', 'issuer', 'serial_number', 'type', 'status',
        'issued_at', 'expires_at', 'auto_renew', 'fullchain',
    ];

    protected function casts(): array
    {
        return [
            'auto_renew' => 'boolean',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) return null;
        return now()->diffInDays($this->expires_at, false);
    }
}
