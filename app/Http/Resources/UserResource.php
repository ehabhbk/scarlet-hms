<?php

namespace App\Http\Resources;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->locale ?? app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'theme' => $this->theme,
            'job_title' => $this->job_title,
            'avatar_path' => $this->avatar_path,
            'is_active' => $this->is_active,
            'two_factor_enabled' => $this->two_factor_enabled,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'name_ar' => $role->name_ar,
                'name_en' => $role->name_en,
            ])->values()),
            'role_names' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permissions' => $this->whenLoaded('roles', fn () => $this->permissionNames()->values()),
            'display_role' => $this->whenLoaded('roles', function () use ($locale) {
                $role = $this->roles->first();

                return $role ? ($locale === 'ar' ? $role->name_ar : $role->name_en) : null;
            }),
            'initials' => $this->initials(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
