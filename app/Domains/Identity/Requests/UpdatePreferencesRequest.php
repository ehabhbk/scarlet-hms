<?php

namespace App\Domains\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
        ];
    }
}
