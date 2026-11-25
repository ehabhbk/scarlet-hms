<?php

namespace App\Domains\Encounters\Events;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Encounter $encounter,
        public User $actor
    ) {}
}
