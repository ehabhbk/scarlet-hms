<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount(['permissions', 'users'])
            ->orderBy('name_en')
            ->get();

        return ApiResponse::success(RoleResource::collection($roles));
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(
            new RoleResource($role->load('permissions')->loadCount(['permissions', 'users']))
        );
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->name === 'super_admin') {
            return ApiResponse::error(__('roles.super_admin_locked'), null, 422);
        }

        $ids = Permission::query()
            ->whereIn('name', $data['permissions'])
            ->pluck('id');

        $role->permissions()->sync($ids);

        return ApiResponse::success(
            new RoleResource($role->fresh()->load('permissions')->loadCount(['permissions', 'users'])),
            __('roles.updated')
        );
    }
}
