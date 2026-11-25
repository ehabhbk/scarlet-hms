<?php

namespace App\Domains\Scheduling\Events;

use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentBooked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public User $actor
    ) {}
}
