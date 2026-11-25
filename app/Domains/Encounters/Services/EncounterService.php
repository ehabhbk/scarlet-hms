<?php

namespace App\Domains\Encounters\Services;

use App\Domains\Encounters\Events\ConsultationCompleted;
use App\Domains\Encounters\Events\ConsultationStarted;
use App\Domains\Encounters\Models\ClinicalNote;
use App\Domains\Encounters\Models\Diagnosis;
use App\Domains\Encounters\Models\Encounter;
use App\Domains\Encounters\Models\VitalSign;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Support\Permissions;
use App\Support\Sequences\SequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EncounterService
{
    public function __construct(private SequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, bool $dispatchEvent = true): Encounter
    {
        if (! empty($data['appointment_id'])) {
            $existing = Encounter::query()
                ->where('appointment_id', $data['appointment_id'])
                ->whereIn('status', [Encounter::STATUS_OPEN, Encounter::STATUS_IN_PROGRESS])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $dateKey = now()->format('Ymd');

        $encounter = DB::transaction(function () use ($data, $actor, $dateKey) {
            $number = $this->sequences->next(
                'encounter:'.$dateKey,
                'ENC-'.$dateKey,
                4
            );

            $encounter = Encounter::create([
                'encounter_number' => $number,
                'patient_id' => $data['patient_id'],
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'],
                'doctor_id' => $data['doctor_id'] ?? $actor->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'type' => $data['type'] ?? Encounter::TYPE_OUTPATIENT,
                'status' => $data['status'] ?? Encounter::STATUS_IN_PROGRESS,
                'started_at' => $data['started_at'] ?? now(),
                'chief_complaint' => $data['chief_complaint'] ?? null,
                'history' => $data['history'] ?? null,
                'examination' => $data['examination'] ?? null,
                'assessment' => $data['assessment'] ?? null,
                'plan' => $data['plan'] ?? null,
                'disposition' => $data['disposition'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($encounter->appointment_id) {
                Appointment::query()
                    ->whereKey($encounter->appointment_id)
                    ->update(['status' => Appointment::STATUS_IN_PROGRESS]);
            }

            return $encounter;
        });

        if ($dispatchEvent) {
            event(new ConsultationStarted($encounter, $actor));
        }

        return $encounter->load(['patient', 'doctor', 'department']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Encounter $encounter, array $data, User $actor): Encounter
    {
        if ($encounter->isSigned()) {
            throw ValidationException::withMessages([
                'encounter' => __('encounters.signed_immutable'),
            ]);
        }

        $allowed = [
            'chief_complaint', 'history', 'examination', 'assessment', 'plan',
            'disposition', 'doctor_id', 'status',
        ];

        $encounter->update(collect($data)->only($allowed)->all());

        return $encounter->fresh();
    }

    public function sign(Encounter $encounter, User $actor): Encounter
    {
        if ($encounter->isSigned()) {
            throw ValidationException::withMessages([
                'encounter' => __('encounters.already_signed'),
            ]);
        }

        $hasDiagnosis = $encounter->diagnoses()->exists();
        $hasClinicalSummary = filled($encounter->assessment) || filled($encounter->plan);

        if (! $hasDiagnosis && ! $hasClinicalSummary) {
            throw ValidationException::withMessages([
                'encounter' => __('encounters.sign_requires_content'),
            ]);
        }

        DB::transaction(function () use ($encounter, $actor) {
            $encounter->update([
                'status' => Encounter::STATUS_COMPLETED,
                'ended_at' => $encounter->ended_at ?? now(),
                'signed_at' => now(),
                'signed_by' => $actor->id,
            ]);

            $encounter->clinicalNotes()
                ->where('status', ClinicalNote::STATUS_DRAFT)
                ->update([
                    'status' => ClinicalNote::STATUS_SIGNED,
                    'signed_at' => now(),
                ]);

            if ($encounter->appointment_id) {
                Appointment::query()
                    ->whereKey($encounter->appointment_id)
                    ->update([
                        'status' => Appointment::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
            }

            QueueTicket::query()
                ->where('encounter_id', $encounter->id)
                ->whereIn('status', [QueueTicket::STATUS_CALLED, QueueTicket::STATUS_IN_PROGRESS])
                ->update([
                    'status' => QueueTicket::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
        });

        event(new ConsultationCompleted($encounter->fresh(), $actor));

        return $encounter->fresh()->load(['patient', 'doctor', 'department', 'diagnoses', 'clinicalNotes']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addVitals(Encounter $encounter, array $data, User $actor): VitalSign
    {
        $weight = isset($data['weight_kg']) ? (float) $data['weight_kg'] : null;
        $height = isset($data['height_cm']) ? (float) $data['height_cm'] : null;

        if ($weight && $height && $height > 0) {
            $data['bmi'] = round($weight / (($height / 100) ** 2), 1);
        }

        return VitalSign::create(array_merge($data, [
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'recorded_by' => $actor->id,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addDiagnosis(Encounter $encounter, array $data, User $actor): Diagnosis
    {
        return Diagnosis::create(array_merge($data, [
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'diagnosed_by' => $actor->id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addNote(Encounter $encounter, array $data, User $actor): ClinicalNote
    {
        $sign = (bool) ($data['sign'] ?? false) && $actor->hasPermission(Permissions::ENCOUNTERS_SIGN);

        return ClinicalNote::create([
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'type' => $data['type'] ?? 'progress',
            'content' => $data['content'],
            'status' => $sign ? ClinicalNote::STATUS_SIGNED : ClinicalNote::STATUS_DRAFT,
            'author_id' => $actor->id,
            'signed_at' => $sign ? now() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Encounter $encounter): array
    {
        $encounter->load([
            'patient.allergies',
            'patient.conditions',
            'doctor',
            'department',
            'branch',
            'vitalSigns' => fn ($query) => $query->latest('recorded_at')->limit(10),
            'diagnoses',
            'clinicalNotes' => fn ($query) => $query->latest('created_at'),
        ]);

        /** @var Patient $patient */
        $patient = $encounter->patient;

        return [
            'encounter' => $encounter,
            'patient' => $patient,
            'allergies' => $patient->allergies,
            'conditions' => $patient->conditions,
            'latest_vitals' => $encounter->vitalSigns->first(),
            'diagnoses' => $encounter->diagnoses,
            'notes' => $encounter->clinicalNotes,
            'is_signed' => $encounter->isSigned(),
        ];
    }
}
