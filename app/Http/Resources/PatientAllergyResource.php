<?php

namespace App\Http\Resources;

use App\Domains\Patients\Models\PatientAllergy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PatientAllergy */
class PatientAllergyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'allergen' => $this->allergen,
            'reaction' => $this->reaction,
            'severity' => $this->severity,
            'status' => $this->status,
            'recorded_by' => $this->recordedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
