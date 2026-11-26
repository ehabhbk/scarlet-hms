<?php

namespace App\Domains\Insurance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceContractService extends Model
{
    protected $fillable = [
        'insurance_contract_id', 'service_code', 'service_name', 'price',
        'patient_share_percent', 'company_share_percent',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'patient_share_percent' => 'decimal:2',
            'company_share_percent' => 'decimal:2',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(InsuranceContract::class);
    }
}
