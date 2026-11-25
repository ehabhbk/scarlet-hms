<?php

namespace App\Domains\Identity\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::USERS_MANAGE) ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'username' => ['sometimes', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
