<?php

namespace App\Http\Resources;

use App\Domains\Patients\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Patient */
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mrn' => $this->mrn,
            'first_name_ar' => $this->first_name_ar,
            'last_name_ar' => $this->last_name_ar,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'full_name_ar' => $this->full_name_ar,
            'full_name_en' => $this->full_name_en,
            'display_name' => $this->display_name,
            'national_id' => $this->national_id,
            'passport_number' => $this->passport_number,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'phone' => $this->phone,
            'alt_phone' => $this->alt_phone,
            'email' => $this->email,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,
            'blood_type' => $this->blood_type,
            'address' => $this->address,
            'city' => $this->city,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'photo_path' => $this->photo_path,
            'status' => $this->status,
            'notes' => $this->notes,
            'registration_branch' => new BranchResource($this->whenLoaded('registrationBranch')),
            'allergies' => PatientAllergyResource::collection($this->whenLoaded('allergies')),
            'conditions' => PatientConditionResource::collection($this->whenLoaded('conditions')),
            'has_allergy_alert' => $this->whenLoaded('allergies', fn () => $this->allergies->contains('status', 'active')),
            'appointments_count' => $this->whenCounted('appointments'),
            'encounters_count' => $this->whenCounted('encounters'),
            'last_visit_at' => $this->when(isset($this->last_visit_at), fn () => $this->last_visit_at),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
