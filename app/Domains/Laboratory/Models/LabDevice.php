<?php

namespace App\Domains\Laboratory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabDevice extends Model
{
    protected $fillable = [
        'code', 'name', 'manufacturer', 'model', 'protocol', 'host', 'port', 'status', 'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'port' => 'integer',
        ];
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(LabTest::class, 'lab_device_test')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LabDeviceMessage::class);
    }
}
