<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Support\Audit\Auditable;
use Database\Factories\QueueTicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicket extends Model
{
    use Auditable, HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_CALLED = 'called';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_NO_SHOW = 'no_show';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_EMERGENCY = 'emergency';

    protected $fillable = [
        'queue_date',
        'branch_id',
        'department_id',
        'doctor_id',
        'patient_id',
        'appointment_id',
        'encounter_id',
        'number',
        'prefix',
        'status',
        'priority',
        'called_at',
        'started_at',
        'completed_at',
        'skip_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'number' => 'integer',
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

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function scopeForDate(Builder $query, ?string $date = null): Builder
    {
        return $query->whereDate('queue_date', $date ?: today());
    }

    public function scopeInDepartment(Builder $query, ?int $departmentId): Builder
    {
        return $departmentId ? $query->where('department_id', $departmentId) : $query;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_WAITING, self::STATUS_CALLED, self::STATUS_IN_PROGRESS]);
    }

    public function displayNumber(): string
    {
        return $this->prefix.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }
}
