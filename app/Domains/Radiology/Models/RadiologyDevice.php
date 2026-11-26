<?php

namespace App\Domains\Radiology\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadiologyDevice extends Model
{
    protected $fillable = [
        'code', 'name', 'manufacturer', 'model', 'protocol', 'ae_title',
        'host', 'port', 'modality', 'status', 'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'port' => 'integer',
        ];
    }

    public function studies(): BelongsToMany
    {
        return $this->belongsToMany(RadiologyStudy::class, 'radiology_device_study')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RadiologyDeviceMessage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(RadiologyOrder::class, 'source_device_id');
    }
}
