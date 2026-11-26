<?php

namespace App\Domains\Inpatient\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaternityRecord extends Model
{
    protected $fillable = ['patient_id', 'doctor_id', 'edd', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'edd' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
