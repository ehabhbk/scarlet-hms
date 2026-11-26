<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $branchId,
        public array $payload = []
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('queues.'.$this->branchId)];
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }
}
