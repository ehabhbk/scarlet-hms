<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Events\AppointmentBooked;
use App\Domains\Scheduling\Events\PatientCheckedIn;
use App\Domains\Scheduling\Models\Appointment;
use App\Support\Sequences\SequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(
        private SequenceService $sequences,
        private QueueService $queues
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function book(array $data, User $actor): Appointment
    {
        $scheduledAt = now()->parse($data['scheduled_at']);
        $dateKey = $scheduledAt->format('Ymd');

        $appointment = DB::transaction(function () use ($data, $actor, $scheduledAt, $dateKey) {
            $number = $this->sequences->next(
                'appointment:'.$dateKey,
                'APT-'.$dateKey,
                4
            );

            return Appointment::create([
                'appointment_number' => $number,
                'patient_id' => $data['patient_id'],
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'],
                'doctor_id' => $data['doctor_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $data['duration_minutes'] ?? 20,
                'type' => $data['type'] ?? 'new',
                'status' => Appointment::STATUS_BOOKED,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
        });

        event(new AppointmentBooked($appointment, $actor));

        return $appointment->load(['patient', 'branch', 'department', 'doctor']);
    }

    public function checkIn(Appointment $appointment, User $actor, string $priority = 'normal'): Appointment
    {
        if (in_array($appointment->status, [Appointment::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => __('appointments.cannot_check_in_cancelled'),
            ]);
        }

        if (in_array($appointment->status, [Appointment::STATUS_CHECKED_IN, Appointment::STATUS_IN_PROGRESS], true)) {
            return $appointment->load(['patient', 'queueTicket', 'doctor', 'department']);
        }

        if ($appointment->status === Appointment::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => __('appointments.cannot_check_in_completed'),
            ]);
        }

        DB::transaction(function () use ($appointment, $actor) {
            $appointment->update([
                'status' => Appointment::STATUS_CHECKED_IN,
                'checked_in_at' => now(),
            ]);
        });

        $queueTicket = $this->queues->issueForAppointment($appointment->fresh(), $actor, $priority);

        event(new PatientCheckedIn($appointment->fresh(), $queueTicket, $actor));

        return $appointment->fresh()->load(['patient', 'queueTicket', 'doctor', 'department']);
    }

    public function start(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => Appointment::STATUS_IN_PROGRESS]);

        return $appointment->fresh();
    }

    public function complete(Appointment $appointment): Appointment
    {
        $appointment->update([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $appointment->fresh();
    }

    public function cancel(Appointment $appointment, string $reason, User $actor): Appointment
    {
        if (in_array($appointment->status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => __('appointments.cannot_cancel'),
            ]);
        }

        DB::transaction(function () use ($appointment, $reason) {
            $appointment->update([
                'status' => Appointment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $appointment->queueTicket()
                ->whereIn('status', ['waiting', 'called'])
                ->update([
                    'status' => 'skipped',
                    'skip_reason' => __('appointments.cancelled_reason'),
                ]);
        });

        return $appointment->fresh();
    }

    public function noShow(Appointment $appointment): Appointment
    {
        if ($appointment->status !== Appointment::STATUS_BOOKED) {
            throw ValidationException::withMessages([
                'status' => __('appointments.no_show_invalid'),
            ]);
        }

        $appointment->update(['status' => Appointment::STATUS_NO_SHOW]);

        return $appointment->fresh();
    }
}
