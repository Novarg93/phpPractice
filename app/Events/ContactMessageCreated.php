<?php

namespace App\Events;

use App\Models\ContactMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class ContactMessageCreated implements ShouldBroadcast
{
    public function __construct(public ContactMessage $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('contact-messages'); // публичный канал
    }

    public function broadcastAs(): string
    {
        return 'created';
    }

    public function broadcastWith(): array
{
    Log::info('Broadcasting ContactMessageCreated', [
        'id' => $this->message->id,
        'email' => $this->message->email,
    ]);

    return [
        'id'    => $this->message->id,
        'email' => $this->message->email,
    ];
}
}