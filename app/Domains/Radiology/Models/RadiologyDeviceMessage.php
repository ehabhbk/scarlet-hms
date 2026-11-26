<?php

namespace App\Domains\Radiology\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyDeviceMessage extends Model
{
    protected $fillable = [
        'radiology_device_id', 'raw_message', 'status', 'message_id',
        'parsed_summary', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RadiologyDevice::class, 'radiology_device_id');
    }
}
