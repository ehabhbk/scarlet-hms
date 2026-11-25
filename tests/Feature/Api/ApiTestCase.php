<?php

namespace Tests\Feature\Api;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessControlSeeder::class, OrganizationSeeder::class]);

        $this->branch = Branch::query()->where('code', 'SCG')->firstOrFail();
        $this->department = Department::query()
            ->where('branch_id', $this->branch->id)
            ->where('code', 'IM')
            ->firstOrFail();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'locale' => 'en',
        ], $attributes));

        $user->roles()->sync(Role::query()->where('name', $role)->pluck('id'));

        return $user;
    }

    protected function actingAsRole(string $role, array $attributes = []): User
    {
        $user = $this->userWithRole($role, $attributes);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
