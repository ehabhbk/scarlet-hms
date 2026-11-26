<?php

namespace App\Domains\Laboratory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTest extends Model
{
    protected $fillable = [
        'code', 'name_ar', 'name_en', 'category', 'unit',
        'reference_range', 'price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(LabTestParameter::class)->orderBy('sort_order');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(LabDevice::class, 'lab_device_test')->withTimestamps();
    }
}
