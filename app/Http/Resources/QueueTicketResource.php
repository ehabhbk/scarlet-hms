<?php

namespace App\Http\Resources;

use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueueTicket */
class QueueTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'queue_date' => $this->queue_date?->toDateString(),
            'number' => $this->number,
            'prefix' => $this->prefix,
            'display_number' => $this->displayNumber(),
            'status' => $this->status,
            'priority' => $this->priority,
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'doctor_id' => $this->doctor_id,
            'doctor' => new UserResource($this->whenLoaded('doctor')),
            'appointment_id' => $this->appointment_id,
            'appointment' => new AppointmentResource($this->whenLoaded('appointment')),
            'encounter_id' => $this->encounter_id,
            'encounter' => new EncounterResource($this->whenLoaded('encounter')),
            'called_at' => $this->called_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'skip_reason' => $this->skip_reason,
            'waiting_since' => $this->created_at?->diffInMinutes(now()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
