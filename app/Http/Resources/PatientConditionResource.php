<?php

namespace App\Http\Resources;

use App\Domains\Patients\Models\PatientCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PatientCondition */
class PatientConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'type' => $this->type,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'diagnosed_on' => $this->diagnosed_on?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'recorded_by' => $this->recordedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
