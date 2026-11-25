<?php

namespace App\Http\Resources;

use App\Domains\Encounters\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Diagnosis */
class DiagnosisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'icd10_code' => $this->icd10_code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'type' => $this->type,
            'certainty' => $this->certainty,
            'notes' => $this->notes,
            'diagnosed_by' => $this->whenLoaded('diagnosedBy', fn () => $this->diagnosedBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
