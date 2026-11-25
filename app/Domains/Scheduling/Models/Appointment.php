<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Support\Audit\Auditable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUS_BOOKED = 'booked';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'appointment_number',
        'patient_id',
        'branch_id',
        'department_id',
        'doctor_id',
        'scheduled_at',
        'duration_minutes',
        'type',
        'status',
        'reason',
        'notes',
        'checked_in_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'duration_minutes' => 'integer',
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

    public function queueTicket(): HasOne
    {
        return $this->hasOne(QueueTicket::class);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('scheduled_at', today());
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    public function scopeForDoctor(Builder $query, ?int $doctorId): Builder
    {
        return $doctorId ? $query->where('doctor_id', $doctorId) : $query;
    }
}
