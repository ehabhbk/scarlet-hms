<?php

namespace App\Http\Resources;

use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_number' => $this->appointment_number,
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'doctor_id' => $this->doctor_id,
            'doctor' => new UserResource($this->whenLoaded('doctor')),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'scheduled_date' => $this->scheduled_at?->toDateString(),
            'scheduled_time' => $this->scheduled_at?->format('H:i'),
            'duration_minutes' => $this->duration_minutes,
            'type' => $this->type,
            'status' => $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'queue_ticket' => new QueueTicketResource($this->whenLoaded('queueTicket')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
