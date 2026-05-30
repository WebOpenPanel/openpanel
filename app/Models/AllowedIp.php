<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllowedIp extends Model
{
    use HasFactory;

    protected $table = 'allowed_ips';

    protected $fillable = [
        'ip_address', 'description', 'added_by',
    ];
}
