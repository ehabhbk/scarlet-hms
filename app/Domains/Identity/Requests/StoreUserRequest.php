<?php

namespace App\Domains\Identity\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::USERS_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
            'theme' => ['nullable', Rule::in(['light', 'dark', 'system'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }
}
