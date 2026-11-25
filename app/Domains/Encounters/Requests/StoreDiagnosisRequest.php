<?php

namespace App\Domains\Encounters\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DIAGNOSES_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'icd10_code' => ['nullable', 'string', 'max:20'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['primary', 'secondary'])],
            'certainty' => ['nullable', Rule::in(['suspected', 'confirmed', 'rule_out'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
