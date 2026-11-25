<?php

namespace App\Listeners;

use App\Domains\Encounters\Events\ConsultationCompleted;
use App\Domains\Encounters\Events\ConsultationStarted;
use App\Domains\Patients\Events\PatientRegistered;
use App\Domains\Scheduling\Events\AppointmentBooked;
use App\Domains\Scheduling\Events\PatientCheckedIn;
use App\Domains\Scheduling\Events\QueueAssigned;
use App\Support\Audit\AuditLogger;

class RecordWorkflowEvent
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(object $event): void
    {
        [$name, $model, $description] = match (true) {
            $event instanceof PatientRegistered => [
                'workflow.patient_registered',
                $event->patient,
                'Patient registered: '.$event->patient->mrn,
            ],
            $event instanceof AppointmentBooked => [
                'workflow.appointment_booked',
                $event->appointment,
                'Appointment booked: '.$event->appointment->appointment_number,
            ],
            $event instanceof PatientCheckedIn => [
                'workflow.patient_checked_in',
                $event->appointment,
                'Patient checked in: '.$event->appointment->appointment_number,
            ],
            $event instanceof QueueAssigned => [
                'workflow.queue_assigned',
                $event->queueTicket,
                'Queue ticket issued: '.$event->queueTicket->displayNumber(),
            ],
            $event instanceof ConsultationStarted => [
                'workflow.consultation_started',
                $event->encounter,
                'Consultation started: '.$event->encounter->encounter_number,
            ],
            $event instanceof ConsultationCompleted => [
                'workflow.consultation_completed',
                $event->encounter,
                'Consultation completed: '.$event->encounter->encounter_number,
            ],
            default => [null, null, null],
        };

        if ($name !== null) {
            $this->auditLogger->log($name, $model, [], [], $description);
        }
    }
}
