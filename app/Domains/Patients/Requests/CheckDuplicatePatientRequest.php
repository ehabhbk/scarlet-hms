<?php

namespace App\Domains\Patients\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class CheckDuplicatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::PATIENTS_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name_ar' => ['nullable', 'string', 'max:100'],
            'last_name_ar' => ['nullable', 'string', 'max:100'],
            'first_name_en' => ['nullable', 'string', 'max:100'],
            'last_name_en' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
        ];
    }
}
