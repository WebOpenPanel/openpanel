<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressSecurityScan extends Model
{
    protected $table = 'wordpress_security_scans';

    protected $fillable = [
        'wordpress_site_id',
        'wp_version',
        'outdated_core',
        'outdated_plugins',
        'outdated_themes',
        'suspicious_files',
        'weak_permissions',
        'result_json',
    ];

    protected function casts(): array
    {
        return [
            'outdated_core' => 'boolean',
            'outdated_plugins' => 'integer',
            'outdated_themes' => 'integer',
            'suspicious_files' => 'integer',
            'weak_permissions' => 'integer',
            'result_json' => 'array',
        ];
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function hasIssues(): bool
    {
        return $this->outdated_core
            || $this->outdated_plugins > 0
            || $this->outdated_themes > 0
            || $this->suspicious_files > 0
            || $this->weak_permissions > 0;
    }
}
