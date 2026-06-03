<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subdomain extends Model
{
    protected $fillable = [
        'user_account_id', 'domain', 'subdomain', 'document_root',
        'ip_address', 'status', 'ssl_enabled',
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

    public function parentDomain()
    {
        return $this->belongsTo(Domain::class, 'domain', 'domain');
    }

    public function fullDomain(): string
    {
        return $this->subdomain . '.' . $this->domain;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
