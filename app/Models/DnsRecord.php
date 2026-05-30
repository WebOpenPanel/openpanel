<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'dns_zone_id', 'name', 'type', 'value', 'ttl', 'priority',
    ];

    public function zone()
    {
        return $this->belongsTo(DnsZone::class, 'dns_zone_id');
    }
}
