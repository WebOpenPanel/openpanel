<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailForwarder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_account_id', 'source_email', 'destination_email', 'status',
    ];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }
}
