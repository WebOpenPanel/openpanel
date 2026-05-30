<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'command', 'minute', 'hour', 'day_of_month',
        'month', 'day_of_week', 'user', 'enabled', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getScheduleAttribute(): string
    {
        return "{$this->minute} {$this->hour} {$this->day_of_month} {$this->month} {$this->day_of_week}";
    }
}
