<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressBackup extends Model
{
    protected $table = 'wordpress_backups';

    protected $fillable = [
        'wordpress_site_id',
        'backup_path',
        'backup_type',
        'size_bytes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
