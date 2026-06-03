<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressTask extends Model
{
    protected $table = 'wordpress_tasks';

    protected $fillable = [
        'wordpress_site_id',
        'type',
        'status',
        'output',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
