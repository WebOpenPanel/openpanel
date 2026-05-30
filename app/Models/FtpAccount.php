<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FtpAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_account_id', 'username', 'password_hash',
        'home_directory', 'quota_mb', 'status',
    ];

    protected $hidden = ['password_hash'];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }
}
