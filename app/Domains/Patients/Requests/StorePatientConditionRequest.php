<?php

namespace App\Domains\Patients\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::PATIENTS_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['chronic', 'surgical', 'family', 'social'])],
            'code' => ['nullable', 'string', 'max:30'],
            'name_ar' => ['required', 'string', 'max:180'],
            'name_en' => ['nullable', 'string', 'max:180'],
            'diagnosed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
