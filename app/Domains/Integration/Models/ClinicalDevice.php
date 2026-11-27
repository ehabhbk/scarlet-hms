<?php

namespace App\Domains\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalDevice extends Model
{
    protected $fillable = [
        'code', 'name', 'category', 'protocol', 'host', 'port', 'direction',
        'poll_enabled', 'poll_interval_seconds', 'poll_query', 'poll_query_encoding',
        'protocol_detected', 'status', 'last_poll_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'poll_enabled' => 'boolean',
            'poll_interval_seconds' => 'integer',
            'last_poll_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ClinicalDeviceMessage::class);
    }
}
