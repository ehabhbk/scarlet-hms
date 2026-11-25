<?php

namespace App\Http\Resources;

use App\Domains\Settings\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Setting */
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->value['value'] ?? null,
            'type' => $this->type,
            'is_public' => $this->is_public,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
