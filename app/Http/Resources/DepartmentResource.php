<?php

namespace App\Http\Resources;

use App\Domains\Identity\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Department */
class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'name' => $this->displayName(),
            'type' => $this->type,
            'location' => $this->location,
            'phone' => $this->phone,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
