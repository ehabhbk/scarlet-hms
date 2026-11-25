<?php

namespace App\Domains\Encounters\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::ENCOUNTERS_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'type' => ['nullable', Rule::in(['outpatient', 'emergency', 'inpatient', 'surgery', 'icu', 'dental'])],
            'status' => ['nullable', Rule::in(['open', 'in_progress'])],
            'chief_complaint' => ['nullable', 'string', 'max:500'],
            'history' => ['nullable', 'string', 'max:20000'],
            'examination' => ['nullable', 'string', 'max:20000'],
            'assessment' => ['nullable', 'string', 'max:20000'],
            'plan' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
