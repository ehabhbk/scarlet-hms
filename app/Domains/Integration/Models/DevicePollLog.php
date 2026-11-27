<?php

namespace App\Domains\Integration\Models;

use Illuminate\Database\Eloquent\Model;

class DevicePollLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_type', 'device_id', 'status', 'frames_count', 'imported_count', 'error', 'polled_at',
    ];

    protected function casts(): array
    {
        return [
            'frames_count' => 'integer',
            'imported_count' => 'integer',
            'polled_at' => 'datetime',
        ];
    }
}
