<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id', 'domain_id', 'user_account_id', 'domain',
        'local_part', 'email', 'password_hash', 'quota_mb',
        'mailbox_path', 'used_bytes', 'status',
    ];

    protected $hidden = ['password_hash'];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }
}
