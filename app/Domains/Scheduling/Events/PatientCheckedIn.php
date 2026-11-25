<?php

namespace App\Domains\Scheduling\Events;

use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientCheckedIn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public QueueTicket $queueTicket,
        public User $actor
    ) {}
}
