<?php

namespace App\Domains\Patients\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::PATIENTS_CREATE) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn ($value) => is_string($value) ? trim($value) : $value,
            $this->only([
                'first_name_ar', 'last_name_ar', 'first_name_en', 'last_name_en',
                'phone', 'alt_phone', 'email', 'national_id', 'address', 'city',
            ])
        ));
    }

    public function rules(): array
    {
        return [
            'first_name_ar' => ['required', 'string', 'max:100'],
            'last_name_ar' => ['required', 'string', 'max:100'],
            'first_name_en' => ['nullable', 'string', 'max:100'],
            'last_name_en' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('patients', 'national_id')],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'unknown'])],
            'nationality' => ['nullable', 'string', 'max:60'],
            'blood_type' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'registration_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'ignore_duplicates' => ['sometimes', 'boolean'],
        ];
    }
}
