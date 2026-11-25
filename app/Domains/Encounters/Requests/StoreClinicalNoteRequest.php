<?php

namespace App\Domains\Encounters\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::CLINICAL_NOTES_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['progress', 'nursing', 'consultation', 'discharge'])],
            'content' => ['required', 'string', 'max:20000'],
            'sign' => ['sometimes', 'boolean'],
        ];
    }
}
