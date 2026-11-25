<?php

namespace App\Support\Sequences;

use App\Domains\Identity\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Atomically generate the next document number for a key.
     *
     * Example: next('mrn', 'SC', 6) => SC-000001
     */
    public function next(string $key, string $prefix, int $padding = 6, string $separator = '-'): string
    {
        return DB::transaction(function () use ($key, $prefix, $padding, $separator) {
            $sequence = DocumentSequence::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'key' => $key,
                    'prefix' => $prefix,
                    'padding' => $padding,
                    'next_value' => 1,
                ]);
            }

            $value = $sequence->next_value;
            $sequence->increment('next_value');

            return $prefix.$separator.str_pad((string) $value, $padding, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Atomically reserve the next integer for a key.
     */
    public function nextNumber(string $key, string $prefix, int $padding = 3): int
    {
        return DB::transaction(function () use ($key, $prefix, $padding) {
            $sequence = DocumentSequence::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'key' => $key,
                    'prefix' => $prefix,
                    'padding' => $padding,
                    'next_value' => 1,
                ]);
            }

            $value = (int) $sequence->next_value;
            $sequence->increment('next_value');

            return $value;
        });
    }

    public function todayKey(string $key): string
    {
        return $key.':'.now()->format('Y-m-d');
    }
}
