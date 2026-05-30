<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'display_name', 'service_name', 'type', 'status',
        'enabled_on_boot', 'monitor_enabled', 'restart_count',
        'last_checked_at', 'last_restarted_at', 'config_path',
    ];

    protected function casts(): array
    {
        return [
            'enabled_on_boot' => 'boolean',
            'monitor_enabled' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_restarted_at' => 'datetime',
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
