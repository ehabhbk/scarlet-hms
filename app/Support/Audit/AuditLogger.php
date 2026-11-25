<?php

namespace App\Support\Audit;

use App\Domains\Identity\Models\User;
use App\Domains\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        ?int $userId = null
    ): AuditLog {
        /** @var User|null $user */
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $userId ?? $user?->id,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'old_values' => $this->redact($oldValues) ?: null,
            'new_values' => $this->redact($newValues) ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => str()->limit((string) request()->userAgent(), 500, ''),
            'request_id' => request()->attributes->get('request_id'),
            'created_at' => now(),
        ]);
    }

    public function viewed(Model $model, ?string $description = null): AuditLog
    {
        return $this->log('viewed', $model, [], [], $description ?? __('audit.viewed_record'));
    }

    public function login(User $user): AuditLog
    {
        return $this->log('auth.login', $user, [], ['user_id' => $user->id], __('audit.login'), $user->id);
    }

    public function logout(User $user): AuditLog
    {
        return $this->log('auth.logout', $user, [], ['user_id' => $user->id], __('audit.logout'), $user->id);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        return Arr::except($values, [
            'password',
            'password_confirmation',
            'remember_token',
            'two_factor_secret',
            'api_token',
            'token',
        ]);
    }
}
