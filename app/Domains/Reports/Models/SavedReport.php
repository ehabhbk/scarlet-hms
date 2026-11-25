<?php

namespace App\Domains\Reports\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    protected $fillable = [
        'name',
        'report_key',
        'filters',
        'user_id',
        'schedule',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
