<?php

namespace App\Domains\Encounters\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diagnosis extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'icd10_code',
        'name_ar',
        'name_en',
        'type',
        'certainty',
        'notes',
        'diagnosed_by',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function diagnosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diagnosed_by');
    }
}
