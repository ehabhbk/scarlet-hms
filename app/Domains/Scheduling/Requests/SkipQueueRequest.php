<?php

namespace App\Domains\Scheduling\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class SkipQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::QUEUES_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:300'],
        ];
    }
}
