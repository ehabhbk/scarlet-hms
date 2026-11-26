<?php

namespace App\Domains\Pharmacy\Models;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use Auditable, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DISPENSED = 'dispensed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'prescription_number', 'patient_id', 'encounter_id', 'appointment_id',
        'branch_id', 'doctor_id', 'status', 'notes', 'prescribed_at',
        'dispensed_at', 'dispensed_by',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_at' => 'datetime',
            'dispensed_at' => 'datetime',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(Dispense::class);
    }
}
