<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_account_id', 'domain', 'email', 'password_hash',
        'quota_mb', 'used_bytes', 'status',
    ];

    protected $hidden = ['password_hash'];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }
}
