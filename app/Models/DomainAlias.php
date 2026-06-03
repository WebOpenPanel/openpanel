<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainAlias extends Model
{
    protected $fillable = [
        'user_account_id', 'domain', 'alias', 'ip_address',
        'status', 'ssl_enabled',
    ];

    protected function casts(): array
    {
        return [
            'ssl_enabled' => 'boolean',
        ];
    }

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }

    public function targetDomain()
    {
        return $this->belongsTo(Domain::class, 'domain', 'domain');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
