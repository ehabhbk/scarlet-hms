<?php

namespace App\Domains\Patients\Models;

use App\Domains\Encounters\Models\ClinicalNote;
use App\Domains\Encounters\Models\Diagnosis;
use App\Domains\Encounters\Models\Encounter;
use App\Domains\Encounters\Models\VitalSign;
use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Support\Audit\Auditable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_DECEASED = 'deceased';

    public const STATUS_MERGED = 'merged';

    protected $fillable = [
        'mrn',
        'first_name_ar',
        'last_name_ar',
        'first_name_en',
        'last_name_en',
        'national_id',
        'passport_number',
        'gender',
        'date_of_birth',
        'phone',
        'alt_phone',
        'email',
        'marital_status',
        'nationality',
        'blood_type',
        'address',
        'city',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'photo_path',
        'status',
        'registration_branch_id',
        'merged_into_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function registrationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'registration_branch_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PatientCondition::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('mrn', 'like', $like)
                ->orWhere('first_name_ar', 'like', $like)
                ->orWhere('last_name_ar', 'like', $like)
                ->orWhere('first_name_en', 'like', $like)
                ->orWhere('last_name_en', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('alt_phone', 'like', $like)
                ->orWhere('national_id', 'like', $like);
        });
    }

    public function getFullNameArAttribute(): string
    {
        return trim($this->first_name_ar.' '.$this->last_name_ar);
    }

    public function getFullNameEnAttribute(): string
    {
        return trim(($this->first_name_en ?: $this->first_name_ar).' '.($this->last_name_en ?: $this->last_name_ar));
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->full_name_ar : $this->full_name_en;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function ageOn(?string $date = null): ?int
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->diffInYears($date ? now()->parse($date) : now());
    }

    public function hasAllergyAlert(): bool
    {
        return $this->allergies()->where('status', 'active')->exists();
    }

    public function activeAllergies()
    {
        return $this->allergies()->where('status', 'active')->get();
    }
}
