<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->value['value'] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general', bool $isPublic = false): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => ['value' => $value],
                'type' => gettype($value),
                'is_public' => $isPublic,
            ]
        );
    }
}
