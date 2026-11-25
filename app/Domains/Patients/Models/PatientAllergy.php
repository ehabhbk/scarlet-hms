<?php

namespace App\Domains\Patients\Models;

use App\Domains\Identity\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientAllergy extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'allergen',
        'reaction',
        'severity',
        'status',
        'recorded_by',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
