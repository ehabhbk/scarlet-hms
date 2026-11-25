<?php

namespace App\Http\Resources;

use App\Domains\Identity\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permission */
class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'module' => $this->module,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
        ];
    }
}
