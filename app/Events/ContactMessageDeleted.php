<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ContactMessageDeleted implements ShouldBroadcast
{
    public function __construct(public int $id) {}

    public function broadcastOn(): Channel
    {
        return new Channel('contact-messages');
    }

    public function broadcastAs(): string
    {
        return 'deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->id];
    }
}