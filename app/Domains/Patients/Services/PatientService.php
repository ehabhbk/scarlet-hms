<?php

namespace App\Domains\Patients\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Patients\Events\PatientRegistered;
use App\Domains\Patients\Models\Patient;
use App\Exceptions\DuplicatePatientException;
use App\Support\Sequences\SequenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PatientService
{
    public function __construct(private SequenceService $sequences) {}

    /**
     * Find potential duplicate patient records using configurable matching rules.
     *
     * Scores are explainable so staff can review before merging. Rules:
     *  - national ID exact: 100
     *  - phone exact: 85
     *  - Arabic full name + same date of birth: 90
     *  - English full name + same date of birth: 85
     *  - full name only: 55
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{patient: Patient, score: int, reasons: array<int, string>}>
     */
    public function duplicates(array $data): Collection
    {
        $query = Patient::query()
            ->whereNotIn('status', [Patient::STATUS_MERGED])
            ->whereNull('deleted_at');

        $query->where(function ($query) use ($data) {
            $nationalId = trim((string) ($data['national_id'] ?? ''));
            $phone = trim((string) ($data['phone'] ?? ''));
            $firstAr = trim((string) ($data['first_name_ar'] ?? ''));
            $lastAr = trim((string) ($data['last_name_ar'] ?? ''));
            $firstEn = trim((string) ($data['first_name_en'] ?? ''));
            $lastEn = trim((string) ($data['last_name_en'] ?? ''));
            $dob = $data['date_of_birth'] ?? null;

            if ($nationalId !== '') {
                $query->orWhere('national_id', $nationalId);
            }

            if ($phone !== '') {
                $query->orWhere('phone', $phone);
            }

            if ($firstAr !== '' && $lastAr !== '') {
                $query->orWhere(function ($q) use ($firstAr, $lastAr, $dob) {
                    $q->where('first_name_ar', $firstAr)->where('last_name_ar', $lastAr);
                    if ($dob) {
                        $q->whereDate('date_of_birth', $dob);
                    }
                });
            }

            if ($firstEn !== '' && $lastEn !== '') {
                $query->orWhere(function ($q) use ($firstEn, $lastEn, $dob) {
                    $q->where('first_name_en', $firstEn)->where('last_name_en', $lastEn);
                    if ($dob) {
                        $q->whereDate('date_of_birth', $dob);
                    }
                });
            }
        });

        return $query->limit(25)->get()
            ->map(function (Patient $patient) use ($data) {
                $score = 0;
                $reasons = [];

                if (! empty($data['national_id']) && $patient->national_id === $data['national_id']) {
                    $score += 100;
                    $reasons[] = 'national_id';
                }

                if (! empty($data['phone']) && $patient->phone === $data['phone']) {
                    $score += 85;
                    $reasons[] = 'phone';
                }

                $sameDob = ! empty($data['date_of_birth'])
                    && $patient->date_of_birth?->toDateString() === $data['date_of_birth'];

                if (
                    ! empty($data['first_name_ar'])
                    && $patient->first_name_ar === $data['first_name_ar']
                    && $patient->last_name_ar === ($data['last_name_ar'] ?? null)
                ) {
                    $score += $sameDob ? 90 : 55;
                    $reasons[] = $sameDob ? 'arabic_name_and_dob' : 'arabic_name';
                }

                if (
                    ! empty($data['first_name_en'])
                    && $patient->first_name_en === $data['first_name_en']
                    && $patient->last_name_en === ($data['last_name_en'] ?? null)
                ) {
                    $score += $sameDob ? 85 : 50;
                    $reasons[] = $sameDob ? 'english_name_and_dob' : 'english_name';
                }

                return [
                    'patient' => $patient,
                    'score' => $score,
                    'reasons' => array_values(array_unique($reasons)),
                ];
            })
            ->filter(fn (array $item) => $item['score'] >= 50)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Register a patient and allocate the network-wide MRN.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $actor, ?int $branchId = null, bool $ignoreDuplicates = false): Patient
    {
        if (! $ignoreDuplicates) {
            $duplicates = $this->duplicates($data);

            if ($duplicates->isNotEmpty()) {
                throw new DuplicatePatientException($duplicates);
            }
        }

        $patient = DB::transaction(function () use ($data, $actor, $branchId) {
            $mrn = $this->sequences->next(
                'patient_mrn',
                (string) config('scarlet.mrn_prefix', 'SC'),
                6
            );

            return Patient::create(array_merge($data, [
                'mrn' => $mrn,
                'status' => $data['status'] ?? Patient::STATUS_ACTIVE,
                'registration_branch_id' => $branchId ?? $actor->branch_id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));
        });

        event(new PatientRegistered($patient, $actor));

        return $patient->load(['registrationBranch', 'allergies', 'conditions']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Patient $patient, array $data, User $actor): Patient
    {
        $data['updated_by'] = $actor->id;

        $patient->update($data);

        return $patient->fresh()->load(['registrationBranch', 'allergies', 'conditions']);
    }

    public function merge(Patient $source, Patient $target, User $actor, ?string $reason = null): Patient
    {
        abort_if($source->is($target), 422, __('patients.cannot_merge_same'));

        DB::transaction(function () use ($source, $target, $actor, $reason) {
            foreach ([
                'appointments',
                'encounters',
                'allergies',
                'conditions',
                'vitalSigns',
                'diagnoses',
                'clinicalNotes',
                'queueTickets',
            ] as $relation) {
                $source->{$relation}()->update(['patient_id' => $target->id]);
            }

            $source->update([
                'status' => Patient::STATUS_MERGED,
                'merged_into_id' => $target->id,
                'updated_by' => $actor->id,
                'notes' => trim(($source->notes ?? '')."\n".__('patients.merged_note', [
                    'mrn' => $target->mrn,
                    'reason' => $reason,
                ])),
            ]);
        });

        return $target->fresh()->load(['allergies', 'conditions']);
    }

    /**
     * Build a chronological, read-only clinical timeline.
     */
    public function timeline(Patient $patient): array
    {
        $items = collect();

        $patient->loadMissing(['allergies', 'conditions']);

        $patient->encounters()
            ->with(['doctor', 'department', 'vitalSigns', 'diagnoses'])
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->each(function ($encounter) use ($items) {
                $items->push([
                    'id' => 'encounter-'.$encounter->id,
                    'type' => 'encounter',
                    'at' => $encounter->started_at?->toIso8601String(),
                    'status' => $encounter->status,
                    'title_ar' => 'زيارة '.$encounter->encounter_number,
                    'title_en' => 'Encounter '.$encounter->encounter_number,
                    'subtitle_ar' => $encounter->department?->name_ar,
                    'subtitle_en' => $encounter->department?->name_en,
                    'reference_id' => $encounter->id,
                    'meta' => [
                        'doctor' => $encounter->doctor?->name,
                        'type' => $encounter->type,
                        'chief_complaint' => $encounter->chief_complaint,
                        'signed_at' => $encounter->signed_at?->toIso8601String(),
                    ],
                ]);
            });

        $patient->appointments()
            ->with(['department', 'doctor'])
            ->latest('scheduled_at')
            ->limit(100)
            ->get()
            ->each(function ($appointment) use ($items) {
                $items->push([
                    'id' => 'appointment-'.$appointment->id,
                    'type' => 'appointment',
                    'at' => $appointment->scheduled_at?->toIso8601String(),
                    'status' => $appointment->status,
                    'title_ar' => 'موعد '.$appointment->appointment_number,
                    'title_en' => 'Appointment '.$appointment->appointment_number,
                    'subtitle_ar' => $appointment->department?->name_ar,
                    'subtitle_en' => $appointment->department?->name_en,
                    'reference_id' => $appointment->id,
                    'meta' => ['doctor' => $appointment->doctor?->name],
                ]);
            });

        $patient->vitalSigns()->latest('recorded_at')->limit(50)->get()
            ->each(function ($vitals) use ($items) {
                $items->push([
                    'id' => 'vitals-'.$vitals->id,
                    'type' => 'vitals',
                    'at' => $vitals->recorded_at?->toIso8601String(),
                    'status' => 'recorded',
                    'title_ar' => 'علامات حيوية',
                    'title_en' => 'Vital signs',
                    'subtitle_ar' => null,
                    'subtitle_en' => null,
                    'reference_id' => $vitals->id,
                    'meta' => [
                        'temperature_c' => $vitals->temperature_c,
                        'heart_rate' => $vitals->heart_rate,
                        'systolic_bp' => $vitals->systolic_bp,
                        'diastolic_bp' => $vitals->diastolic_bp,
                        'oxygen_saturation' => $vitals->oxygen_saturation,
                    ],
                ]);
            });

        $patient->diagnoses()->latest('created_at')->limit(50)->get()
            ->each(function ($diagnosis) use ($items) {
                $items->push([
                    'id' => 'diagnosis-'.$diagnosis->id,
                    'type' => 'diagnosis',
                    'at' => $diagnosis->created_at?->toIso8601String(),
                    'status' => $diagnosis->certainty,
                    'title_ar' => $diagnosis->name_ar,
                    'title_en' => $diagnosis->name_en ?: $diagnosis->name_ar,
                    'subtitle_ar' => $diagnosis->icd10_code,
                    'subtitle_en' => $diagnosis->icd10_code,
                    'reference_id' => $diagnosis->id,
                    'meta' => ['type' => $diagnosis->type],
                ]);
            });

        $patient->clinicalNotes()->latest('created_at')->limit(50)->get()
            ->each(function ($note) use ($items) {
                $items->push([
                    'id' => 'note-'.$note->id,
                    'type' => 'clinical_note',
                    'at' => $note->created_at?->toIso8601String(),
                    'status' => $note->status,
                    'title_ar' => 'ملاحظة سريرية',
                    'title_en' => 'Clinical note',
                    'subtitle_ar' => null,
                    'subtitle_en' => null,
                    'reference_id' => $note->id,
                    'meta' => ['type' => $note->type],
                ]);
            });

        $patient->allergies->each(function ($allergy) use ($items) {
            $items->push([
                'id' => 'allergy-'.$allergy->id,
                'type' => 'allergy',
                'at' => $allergy->created_at?->toIso8601String(),
                'status' => $allergy->status,
                'title_ar' => 'حساسية: '.$allergy->allergen,
                'title_en' => 'Allergy: '.$allergy->allergen,
                'subtitle_ar' => $allergy->reaction,
                'subtitle_en' => $allergy->reaction,
                'reference_id' => $allergy->id,
                'meta' => ['severity' => $allergy->severity],
            ]);
        });

        $patient->conditions->each(function ($condition) use ($items) {
            $items->push([
                'id' => 'condition-'.$condition->id,
                'type' => 'condition',
                'at' => $condition->created_at?->toIso8601String(),
                'status' => $condition->status,
                'title_ar' => $condition->name_ar,
                'title_en' => $condition->name_en ?: $condition->name_ar,
                'subtitle_ar' => $condition->type,
                'subtitle_en' => $condition->type,
                'reference_id' => $condition->id,
                'meta' => ['code' => $condition->code],
            ]);
        });

        return $items
            ->filter(fn (array $item) => $item['at'] !== null)
            ->sortByDesc('at')
            ->take(200)
            ->values()
            ->all();
    }
}
