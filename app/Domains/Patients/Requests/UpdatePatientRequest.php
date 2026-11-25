<?php

namespace App\Domains\Patients\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::PATIENTS_UPDATE) ?? false;
    }

    public function rules(): array
    {
        $patientId = $this->route('patient')?->id ?? $this->route('patient');

        return [
            'first_name_ar' => ['sometimes', 'string', 'max:100'],
            'last_name_ar' => ['sometimes', 'string', 'max:100'],
            'first_name_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('patients', 'national_id')->ignore($patientId)],
            'passport_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'date', 'before_or_equal:today'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'alt_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'marital_status' => ['sometimes', 'nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'unknown'])],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:60'],
            'blood_type' => ['sometimes', 'nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'deceased'])],
        ];
    }
}
