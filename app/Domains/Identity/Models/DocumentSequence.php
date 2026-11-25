<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'key',
        'prefix',
        'padding',
        'next_value',
    ];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'next_value' => 'integer',
        ];
    }
}
