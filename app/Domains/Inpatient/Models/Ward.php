<?php

namespace App\Domains\Inpatient\Models;

use App\Domains\Identity\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    protected $fillable = ['branch_id', 'code', 'name_ar', 'name_en', 'type'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
