<?php

namespace App\Http\Resources;

use App\Domains\Encounters\Models\Encounter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Encounter */
class EncounterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_number' => $this->encounter_number,
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'doctor_id' => $this->doctor_id,
            'doctor' => new UserResource($this->whenLoaded('doctor')),
            'appointment_id' => $this->appointment_id,
            'appointment' => new AppointmentResource($this->whenLoaded('appointment')),
            'type' => $this->type,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'chief_complaint' => $this->chief_complaint,
            'history' => $this->history,
            'examination' => $this->examination,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'disposition' => $this->disposition,
            'is_signed' => $this->isSigned(),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_by' => $this->whenLoaded('signedBy', fn () => $this->signedBy?->name),
            'vital_signs' => VitalSignResource::collection($this->whenLoaded('vitalSigns')),
            'diagnoses' => DiagnosisResource::collection($this->whenLoaded('diagnoses')),
            'clinical_notes' => ClinicalNoteResource::collection($this->whenLoaded('clinicalNotes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
