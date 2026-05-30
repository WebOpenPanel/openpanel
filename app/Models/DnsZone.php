<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DnsZone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_account_id', 'domain', 'nameserver1', 'nameserver2',
        'nameserver1_ip', 'nameserver2_ip', 'ttl', 'serial', 'status',
    ];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }

    public function records()
    {
        return $this->hasMany(DnsRecord::class);
    }
}
