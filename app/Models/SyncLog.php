<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'started_at',
        'completed_at',
        'status',
        'total_records',
        'processed_records',
        'failed_records',
        'error_message',
        'failures_log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failures_log' => 'array',
    ];
}
