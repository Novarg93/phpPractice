<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ContactMessageStatusChanged implements ShouldBroadcast
{
    public function __construct(
        public int $id,
        public string $status,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('contact-messages');
    }

    public function broadcastAs(): string
    {
        return 'status-changed';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->id, 'status' => $this->status];
    }
}