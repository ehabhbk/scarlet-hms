<?php

namespace App\Domains\Encounters\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::ENCOUNTERS_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'chief_complaint' => ['sometimes', 'nullable', 'string', 'max:500'],
            'history' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'examination' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'assessment' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'disposition' => ['sometimes', 'nullable', 'string', 'max:50'],
            'doctor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'completed'])],
        ];
    }
}
