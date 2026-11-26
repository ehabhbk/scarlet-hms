<?php

namespace App\Domains\Insurance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceProvider extends Model
{
    protected $fillable = [
        'code', 'name', 'contact_phone', 'contact_email',
        'coverage_percent', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'coverage_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function patientInsurances(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }
}
