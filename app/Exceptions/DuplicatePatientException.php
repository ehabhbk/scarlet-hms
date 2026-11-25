<?php

namespace App\Exceptions;

use App\Domains\Patients\Models\Patient;
use Exception;
use Illuminate\Support\Collection;

class DuplicatePatientException extends Exception
{
    /**
     * @param  Collection<int, array{patient: Patient, score: int, reasons: array<int, string>}>  $candidates
     */
    public function __construct(public Collection $candidates)
    {
        parent::__construct(__('patients.duplicate_detected'));
    }

    public function candidates(): array
    {
        return $this->candidates->map(fn (array $item) => [
            'id' => $item['patient']->id,
            'mrn' => $item['patient']->mrn,
            'name' => $item['patient']->display_name,
            'phone' => $item['patient']->phone,
            'date_of_birth' => $item['patient']->date_of_birth?->toDateString(),
            'score' => $item['score'],
            'reasons' => $item['reasons'],
        ])->values()->all();
    }
}
