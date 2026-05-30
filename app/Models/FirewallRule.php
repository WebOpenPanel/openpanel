<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirewallRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'action', 'protocol', 'source_ip', 'source_port',
        'destination_ip', 'destination_port', 'direction',
        'priority', 'comment', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
