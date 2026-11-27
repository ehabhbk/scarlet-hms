<?php

namespace App\Domains\Integration\Models;

use App\Domains\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalDeviceMessage extends Model
{
    protected $fillable = [
        'clinical_device_id', 'patient_id', 'protocol', 'message_id', 'patient_mrn',
        'reference', 'raw_message', 'canonical_json', 'parsed_summary', 'status', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'canonical_json' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ClinicalDevice::class, 'clinical_device_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
