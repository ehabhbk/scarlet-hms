<?php

namespace App\Domains\Patients\Events;

use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Patient $patient,
        public User $actor
    ) {}
}
