<?php

namespace App\Domains\Laboratory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabDeviceMessage extends Model
{
    protected $fillable = [
        'lab_device_id', 'raw_message', 'status', 'parsed_summary', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(LabDevice::class, 'lab_device_id');
    }
}
