<?php

namespace App\Domains\Scheduling\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckInAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::APPOINTMENTS_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'priority' => ['nullable', Rule::in(['normal', 'urgent', 'emergency'])],
        ];
    }
}
