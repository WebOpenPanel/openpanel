<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'enabled', 'frequency', 'retention_days', 'accounts',
        'include_databases', 'include_email', 'include_files',
        'destination', 'remote_host', 'remote_user', 'remote_path',
        'remote_port', 'notification_email',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'accounts' => 'array',
            'include_databases' => 'boolean',
            'include_email' => 'boolean',
            'include_files' => 'boolean',
        ];
    }
}
