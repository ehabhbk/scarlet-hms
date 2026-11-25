<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Encounters\Services\EncounterService;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Events\QueueAssigned;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Support\Sequences\SequenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueService
{
    public function __construct(
        private SequenceService $sequences,
        private EncounterService $encounters
    ) {}

    public function issueForAppointment(Appointment $appointment, User $actor, string $priority = 'normal'): QueueTicket
    {
        $existing = QueueTicket::query()
            ->where('appointment_id', $appointment->id)
            ->whereDate('queue_date', today())
            ->whereIn('status', [
                QueueTicket::STATUS_WAITING,
                QueueTicket::STATUS_CALLED,
                QueueTicket::STATUS_IN_PROGRESS,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->issue(
            patient: $appointment->patient,
            branchId: $appointment->branch_id,
            departmentId: $appointment->department_id,
            doctorId: $appointment->doctor_id,
            actor: $actor,
            appointment: $appointment,
            priority: $priority
        );
    }

    public function issue(
        Patient $patient,
        int $branchId,
        int $departmentId,
        ?int $doctorId,
        User $actor,
        ?Appointment $appointment = null,
        ?int $encounterId = null,
        string $priority = 'normal'
    ): QueueTicket {
        $existing = QueueTicket::query()
            ->whereDate('queue_date', today())
            ->where('patient_id', $patient->id)
            ->where('department_id', $departmentId)
            ->whereIn('status', [
                QueueTicket::STATUS_WAITING,
                QueueTicket::STATUS_CALLED,
                QueueTicket::STATUS_IN_PROGRESS,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        $date = today()->toDateString();
        $prefix = match ($priority) {
            QueueTicket::PRIORITY_EMERGENCY => 'E',
            QueueTicket::PRIORITY_URGENT => 'U',
            default => 'Q',
        };

        $number = $this->sequences->nextNumber(
            'queue:'.$branchId.':'.$departmentId.':'.$date,
            $prefix,
            3
        );

        $ticket = QueueTicket::create([
            'queue_date' => $date,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'doctor_id' => $doctorId,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'encounter_id' => $encounterId,
            'number' => $number,
            'prefix' => $prefix,
            'status' => QueueTicket::STATUS_WAITING,
            'priority' => $priority,
            'created_by' => $actor->id,
        ]);

        event(new QueueAssigned($ticket, $actor));

        return $ticket->load(['patient', 'department', 'doctor']);
    }

    public function call(QueueTicket $ticket, User $actor): QueueTicket
    {
        if (in_array($ticket->status, [QueueTicket::STATUS_CALLED, QueueTicket::STATUS_IN_PROGRESS], true)) {
            return $ticket;
        }

        if (! in_array($ticket->status, [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_SKIPPED, QueueTicket::STATUS_NO_SHOW], true)) {
            throw ValidationException::withMessages([
                'status' => __('queues.cannot_call'),
            ]);
        }

        $ticket->update([
            'status' => QueueTicket::STATUS_CALLED,
            'called_at' => now(),
            'skip_reason' => null,
        ]);

        return $ticket->fresh(['patient', 'department', 'doctor']);
    }

    public function callNext(int $branchId, int $departmentId, User $actor, ?int $doctorId = null): ?QueueTicket
    {
        $query = QueueTicket::query()
            ->whereDate('queue_date', today())
            ->where('branch_id', $branchId)
            ->where('department_id', $departmentId)
            ->where('status', QueueTicket::STATUS_WAITING);

        if ($doctorId) {
            $query->where(function ($q) use ($doctorId) {
                $q->whereNull('doctor_id')->orWhere('doctor_id', $doctorId);
            });
        }

        $next = $query
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('number')
            ->lockForUpdate()
            ->first();

        if (! $next) {
            return null;
        }

        return $this->call($next, $actor);
    }

    public function start(QueueTicket $ticket, User $actor): QueueTicket
    {
        if ($ticket->status === QueueTicket::STATUS_IN_PROGRESS) {
            return $ticket;
        }

        if (! in_array($ticket->status, [QueueTicket::STATUS_CALLED, QueueTicket::STATUS_WAITING], true)) {
            throw ValidationException::withMessages([
                'status' => __('queues.cannot_start'),
            ]);
        }

        $encounter = null;

        DB::transaction(function () use ($ticket, $actor, &$encounter) {
            if ($ticket->encounter_id) {
                $encounter = $ticket->encounter;
            } else {
                $encounter = $this->encounters->create([
                    'patient_id' => $ticket->patient_id,
                    'branch_id' => $ticket->branch_id,
                    'department_id' => $ticket->department_id,
                    'doctor_id' => $ticket->doctor_id ?? $actor->id,
                    'appointment_id' => $ticket->appointment_id,
                    'type' => 'outpatient',
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'chief_complaint' => $ticket->appointment?->reason,
                ], $actor);
            }

            $ticket->update([
                'status' => QueueTicket::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'called_at' => $ticket->called_at ?? now(),
                'encounter_id' => $encounter->id,
            ]);

            if ($ticket->appointment) {
                $ticket->appointment->update([
                    'status' => Appointment::STATUS_IN_PROGRESS,
                ]);
            }
        });

        return $ticket->fresh(['patient', 'department', 'doctor', 'encounter']);
    }

    public function complete(QueueTicket $ticket, User $actor): QueueTicket
    {
        if ($ticket->status === QueueTicket::STATUS_COMPLETED) {
            return $ticket;
        }

        if (! in_array($ticket->status, [QueueTicket::STATUS_IN_PROGRESS, QueueTicket::STATUS_CALLED], true)) {
            throw ValidationException::withMessages([
                'status' => __('queues.cannot_complete'),
            ]);
        }

        DB::transaction(function () use ($ticket) {
            $ticket->update([
                'status' => QueueTicket::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            if ($ticket->appointment) {
                $ticket->appointment->update([
                    'status' => Appointment::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }

            if ($ticket->encounter_id) {
                $ticket->encounter()->update([
                    'ended_at' => now(),
                ]);
            }
        });

        return $ticket->fresh(['patient', 'department', 'doctor', 'encounter']);
    }

    public function skip(QueueTicket $ticket, string $reason): QueueTicket
    {
        if (! in_array($ticket->status, [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED], true)) {
            throw ValidationException::withMessages([
                'status' => __('queues.cannot_skip'),
            ]);
        }

        $ticket->update([
            'status' => QueueTicket::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ]);

        return $ticket->fresh();
    }

    public function markNoShow(QueueTicket $ticket): QueueTicket
    {
        if (! in_array($ticket->status, [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED], true)) {
            throw ValidationException::withMessages([
                'status' => __('queues.cannot_mark_no_show'),
            ]);
        }

        $ticket->update(['status' => QueueTicket::STATUS_NO_SHOW]);

        return $ticket->fresh();
    }

    public function recall(QueueTicket $ticket): QueueTicket
    {
        if (! in_array($ticket->status, [QueueTicket::STATUS_SKIPPED, QueueTicket::STATUS_NO_SHOW], true)) {
            throw ValidationException::withMessages([
                'status' => __('queues.cannot_recall'),
            ]);
        }

        $ticket->update([
            'status' => QueueTicket::STATUS_CALLED,
            'called_at' => now(),
        ]);

        return $ticket->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function board(int $branchId, ?int $departmentId, ?string $date = null): array
    {
        $date = $date ?: today()->toDateString();

        $tickets = QueueTicket::query()
            ->with(['patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en,date_of_birth,gender', 'department:id,name_ar,name_en', 'doctor:id,name'])
            ->where('branch_id', $branchId)
            ->whereDate('queue_date', $date)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'called' THEN 1 WHEN 'waiting' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")
            ->orderBy('number')
            ->get();

        $counts = $tickets->groupBy('status')->map->count();

        return [
            'date' => $date,
            'counts' => [
                'waiting' => (int) ($counts[QueueTicket::STATUS_WAITING] ?? 0),
                'called' => (int) ($counts[QueueTicket::STATUS_CALLED] ?? 0),
                'in_progress' => (int) ($counts[QueueTicket::STATUS_IN_PROGRESS] ?? 0),
                'completed' => (int) ($counts[QueueTicket::STATUS_COMPLETED] ?? 0),
                'skipped' => (int) ($counts[QueueTicket::STATUS_SKIPPED] ?? 0),
                'no_show' => (int) ($counts[QueueTicket::STATUS_NO_SHOW] ?? 0),
            ],
            'tickets' => $tickets,
        ];
    }

    /**
     * Privacy-safe waiting room payload (no patient identifiers).
     *
     * @return array<string, mixed>
     */
    public function publicBoard(int $branchId, ?int $departmentId, ?string $date = null): array
    {
        $date = $date ?: today()->toDateString();

        $tickets = QueueTicket::query()
            ->with(['department:id,name_ar,name_en', 'doctor:id,name'])
            ->where('branch_id', $branchId)
            ->whereDate('queue_date', $date)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->whereIn('status', [
                QueueTicket::STATUS_WAITING,
                QueueTicket::STATUS_CALLED,
                QueueTicket::STATUS_IN_PROGRESS,
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'called' THEN 1 ELSE 2 END")
            ->orderBy('number')
            ->get();

        $called = $tickets->whereIn('status', [QueueTicket::STATUS_CALLED, QueueTicket::STATUS_IN_PROGRESS])->values();
        $waiting = $tickets->where('status', QueueTicket::STATUS_WAITING)->values();

        return [
            'date' => $date,
            'current' => $called->first() ? $this->publicTicket($called->first()) : null,
            'called' => $called->take(5)->map(fn ($ticket) => $this->publicTicket($ticket))->values(),
            'next' => $waiting->take(6)->map(fn ($ticket) => $this->publicTicket($ticket))->values(),
            'waiting_count' => $waiting->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicTicket(QueueTicket $ticket): array
    {
        return [
            'number' => $ticket->displayNumber(),
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'department_ar' => $ticket->department?->name_ar,
            'department_en' => $ticket->department?->name_en,
            'doctor' => $ticket->doctor?->name,
        ];
    }
}
