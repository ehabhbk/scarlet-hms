<?php

namespace App\Domains\Radiology\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyImage extends Model
{
    protected $fillable = [
        'radiology_order_id',
        'radiology_device_id',
        'radiology_device_message_id',
        'disk',
        'path',
        'sop_uid',
        'study_uid',
        'modality',
        'mime_type',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RadiologyOrder::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RadiologyDevice::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(RadiologyDeviceMessage::class);
    }
}
