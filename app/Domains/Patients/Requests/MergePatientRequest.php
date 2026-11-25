<?php

namespace App\Domains\Patients\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class MergePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::PATIENTS_MERGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'target_patient_id' => ['required', 'integer', 'exists:patients,id', 'different:patient'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
