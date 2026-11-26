<?php

namespace App\Domains\Laboratory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTestParameter extends Model
{
    protected $fillable = [
        'lab_test_id', 'code', 'name_ar', 'name_en', 'unit', 'reference_range', 'sort_order',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function resultValues(): HasMany
    {
        return $this->hasMany(LabResultValue::class);
    }
}
