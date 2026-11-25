<?php

namespace App\Domains\Encounters\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    use Auditable;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'temperature_c',
        'heart_rate',
        'respiratory_rate',
        'systolic_bp',
        'diastolic_bp',
        'oxygen_saturation',
        'weight_kg',
        'height_cm',
        'bmi',
        'pain_score',
        'notes',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature_c' => 'decimal:1',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'bmi' => 'decimal:1',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
