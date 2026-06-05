<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_account_id', 'domain', 'document_root', 'ip_address',
        'type', 'ssl_enabled', 'ssl_certificate', 'ssl_key', 'ssl_ca',
        'ssl_provider', 'ssl_expires_at', 'auto_ssl', 'force_https',
        'custom_vhost_config', 'redirect_url', 'redirect_type',
    ];

    protected function casts(): array
    {
        return [
            'ssl_enabled' => 'boolean',
            'auto_ssl' => 'boolean',
            'force_https' => 'boolean',
            'ssl_expires_at' => 'datetime',
        ];
    }

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }

    public function sslCertificates()
    {
        return $this->hasMany(SslCertificate::class, 'domain', 'domain');
    }
}
