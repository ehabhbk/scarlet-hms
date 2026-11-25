<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function login(string $identifier, string $password, string $deviceName = 'web'): array
    {
        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.inactive'),
            ]);
        }

        $token = $user->createToken($deviceName, ['*'])->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->login($user);

        return [
            'user' => $user->fresh()->load(['roles.permissions', 'branch', 'department', 'branches']),
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        $this->auditLogger->logout($user);
    }

    public function updatePreferences(User $user, array $attributes): User
    {
        $user->update(collect($attributes)->only(['locale', 'theme'])->all());

        return $user->fresh()->load(['roles.permissions', 'branch', 'department', 'branches']);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.password_incorrect'),
            ]);
        }

        $user->update(['password' => $newPassword]);
        $user->tokens()->delete();
        $this->auditLogger->log('auth.password_changed', $user, [], [], 'Password changed');
    }
}
