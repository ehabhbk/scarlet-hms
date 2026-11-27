<?php

namespace App\Domains\Laboratory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabDevice extends Model
{
    protected $fillable = [
        'code', 'name', 'manufacturer', 'model', 'protocol', 'host', 'port', 'status', 'last_sync_at',
        'direction', 'poll_enabled', 'poll_interval_seconds', 'poll_query', 'poll_query_encoding',
        'protocol_detected', 'last_poll_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'last_poll_at' => 'datetime',
            'port' => 'integer',
            'poll_enabled' => 'boolean',
            'poll_interval_seconds' => 'integer',
        ];
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(LabTest::class, 'lab_device_test')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LabDeviceMessage::class);
    }
}
