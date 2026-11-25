<?php

namespace App\Domains\Encounters\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalNote extends Model
{
    use Auditable, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_AMENDED = 'amended';

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'type',
        'content',
        'status',
        'author_id',
        'signed_at',
        'amended_from_id',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function amendedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amended_from_id');
    }
}
