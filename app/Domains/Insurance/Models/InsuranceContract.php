<?php

namespace App\Domains\Insurance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceContract extends Model
{
    protected $fillable = [
        'insurance_provider_id', 'contract_number', 'start_date', 'end_date',
        'patient_share_percent', 'company_share_percent', 'terms', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'patient_share_percent' => 'decimal:2',
            'company_share_percent' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(InsuranceContractService::class);
    }
}
