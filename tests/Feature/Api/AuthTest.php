<?php

namespace Tests\Feature\Api;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthTest extends ApiTestCase
{
    public function test_user_can_login_with_username_and_receives_abilities(): void
    {
        $user = $this->userWithRole('doctor', [
            'username' => 'doctor.test',
            'password' => 'Secret@12345',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'doctor.test',
            'password' => 'Secret@12345',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'doctor.test')
            ->assertJsonPath('data.user.role_names.0', 'doctor')
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'user' => ['permissions']],
            ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invalid_credentials_return_validation_error(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'missing',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['identifier']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->userWithRole('doctor', [
            'username' => 'inactive.doctor',
            'password' => 'Secret@12345',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'inactive.doctor',
            'password' => 'Secret@12345',
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_read_profile_and_logout(): void
    {
        $user = $this->actingAsRole('doctor');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_unauthenticated_requests_receive_json_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_password_change_revokes_tokens(): void
    {
        $user = $this->userWithRole('doctor', [
            'password' => 'Secret@12345',
        ]);

        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'Secret@12345',
                'password' => 'NewSecret@12345',
                'password_confirmation' => 'NewSecret@12345',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NewSecret@12345', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }
}
