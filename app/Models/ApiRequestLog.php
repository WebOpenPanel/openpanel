<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'token_id', 'method', 'path', 'ip', 'status_code',
        'duration_ms', 'action', 'params_summary', 'error',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function token()
    {
        return $this->belongsTo(ApiToken::class, 'token_id');
    }
}
