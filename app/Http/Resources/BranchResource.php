<?php

namespace App\Http\Resources;

use App\Domains\Identity\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'name' => $this->displayName(),
            'type' => $this->type,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'departments_count' => $this->whenCounted('departments'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
