<?php

namespace App\Domains\Encounters\Models;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use App\Support\Audit\Auditable;
use Database\Factories\EncounterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encounter extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_OUTPATIENT = 'outpatient';

    public const TYPE_EMERGENCY = 'emergency';

    public const TYPE_INPATIENT = 'inpatient';

    public const TYPE_SURGERY = 'surgery';

    public const TYPE_ICU = 'icu';

    public const TYPE_DENTAL = 'dental';

    protected $fillable = [
        'encounter_number',
        'patient_id',
        'branch_id',
        'department_id',
        'doctor_id',
        'appointment_id',
        'type',
        'status',
        'started_at',
        'ended_at',
        'chief_complaint',
        'history',
        'examination',
        'assessment',
        'plan',
        'disposition',
        'signed_at',
        'signed_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function latestVitals(): ?VitalSign
    {
        return $this->vitalSigns()->latest('recorded_at')->first();
    }
}
