<?php

namespace App\Domains\Patients\Models;

use App\Domains\Identity\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientCondition extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'type',
        'code',
        'name_ar',
        'name_en',
        'diagnosed_on',
        'status',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'diagnosed_on' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
