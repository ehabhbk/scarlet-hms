<?php

namespace App\Domains\Encounters\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreVitalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::VITALS_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'heart_rate' => ['nullable', 'integer', 'between:20,300'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:0.5,500'],
            'height_cm' => ['nullable', 'numeric', 'between:20,300'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
