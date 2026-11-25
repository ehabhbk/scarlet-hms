<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? 'ar',
                'theme' => $data['theme'] ?? 'system',
                'branch_id' => $data['branch_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $roles = Role::query()->whereIn('name', $data['roles'] ?? [])->pluck('id');
            $user->roles()->sync($roles);

            $branchIds = $data['branch_ids'] ?? array_filter([$data['branch_id'] ?? null]);

            foreach ($branchIds as $index => $branchId) {
                $user->branches()->syncWithoutDetaching([
                    $branchId => ['is_default' => $index === 0],
                ]);
            }

            return $user->load(['roles.permissions', 'branch', 'department', 'branches']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update(collect($data)->only([
                'name', 'username', 'email', 'phone', 'locale', 'theme',
                'branch_id', 'department_id', 'job_title', 'is_active',
            ])->all());

            if (isset($data['roles'])) {
                $roles = Role::query()->whereIn('name', $data['roles'])->pluck('id');
                $user->roles()->sync($roles);
            }

            if (isset($data['password']) && filled($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

            return $user->fresh()->load(['roles.permissions', 'branch', 'department', 'branches']);
        });
    }

    public function deactivate(User $user): User
    {
        if ($user->isSuperAdmin() && User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => __('users.cannot_deactivate_last_admin'),
            ]);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return $user->fresh();
    }
}
