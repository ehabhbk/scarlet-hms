<?php

namespace App\Domains\Radiology\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RadiologyStudy extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'modality', 'price', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(RadiologyDevice::class, 'radiology_device_study')->withTimestamps();
    }
}
