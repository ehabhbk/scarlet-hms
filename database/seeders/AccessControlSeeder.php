<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $labels = Permissions::labels();

        foreach (Permissions::all() as $name) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'module' => Str::before($name, '.'),
                    'name_ar' => $labels[$name]['ar'] ?? $name,
                    'name_en' => $labels[$name]['en'] ?? $name,
                ]
            );
        }

        foreach (Permissions::roles() as $name => $definition) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'name_ar' => $definition['ar'],
                    'name_en' => $definition['en'],
                    'description' => $definition['en'],
                    'is_system' => true,
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('name', $definition['permissions'])
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        $this->command?->info('Access control seeded: '.count(Permissions::all()).' permissions, '.count(Permissions::roles()).' roles.');
    }
}
