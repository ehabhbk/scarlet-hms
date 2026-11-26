<?php

namespace App\Domains\Radiology\Models;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyOrder extends Model
{
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_RESULT_RECEIVED = 'result_received';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number', 'patient_id', 'encounter_id', 'branch_id',
        'doctor_id', 'modality', 'study_description', 'status',
        'ordered_at', 'completed_at', 'report', 'reported_by',
        'source', 'source_device_id', 'source_message_id',
        'radiology_study_id', 'result_received_at',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
            'result_received_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(RadiologyStudy::class, 'radiology_study_id');
    }

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(RadiologyDevice::class, 'source_device_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(RadiologyDeviceMessage::class, 'source_message_id');
    }
}
