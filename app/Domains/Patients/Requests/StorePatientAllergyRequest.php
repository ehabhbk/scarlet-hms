<?php

namespace App\Domains\Patients\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::PATIENTS_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'allergen' => ['required', 'string', 'max:150'],
            'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(['mild', 'moderate', 'severe', 'life_threatening'])],
        ];
    }
}
