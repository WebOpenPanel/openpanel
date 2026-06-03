<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WordPressSite extends Model
{
    use SoftDeletes;

    protected $table = 'wordpress_sites';

    protected $fillable = [
        'user_account_id',
        'domain_id',
        'domain',
        'install_path',
        'site_url',
        'admin_user',
        'admin_email',
        'db_name',
        'db_user',
        'db_password_encrypted',
        'wp_version',
        'php_version',
        'stack_name',
        'redis_enabled',
        'redis_prefix',
        'redis_db_index',
        'varnish_enabled',
        'ssl_enabled',
        'status',
        'performance_profile',
        'php_fpm_pm',
        'php_fpm_max_children',
        'php_fpm_memory_limit',
        'php_fpm_max_execution_time',
        'php_fpm_upload_max_filesize',
        'wp_cron_disabled',
        'wp_cron_interval',
        'last_scan_at',
        'last_backup_at',
    ];

    protected function casts(): array
    {
        return [
            'db_password_encrypted' => 'encrypted',
            'redis_enabled' => 'boolean',
            'redis_db_index' => 'integer',
            'varnish_enabled' => 'boolean',
            'ssl_enabled' => 'boolean',
            'php_fpm_max_children' => 'integer',
            'php_fpm_memory_limit' => 'integer',
            'php_fpm_max_execution_time' => 'integer',
            'php_fpm_upload_max_filesize' => 'integer',
            'wp_cron_disabled' => 'boolean',
            'wp_cron_interval' => 'integer',
            'last_scan_at' => 'datetime',
            'last_backup_at' => 'datetime',
        ];
    }

    public function userAccount(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WordPressTask::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(WordPressBackup::class);
    }

    public function securityScans(): HasMany
    {
        return $this->hasMany(WordPressSecurityScan::class);
    }

    public function getDbPasswordAttribute(): string
    {
        return $this->db_password_encrypted;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
