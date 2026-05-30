<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailAutoresponder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_account_id', 'email', 'subject', 'body', 'status',
    ];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }
}
