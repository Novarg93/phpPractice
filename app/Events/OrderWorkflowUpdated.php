<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Log;

class OrderWorkflowUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    /** Важное: бросать после commit транзакции */
    public bool $afterCommit = true;

    public function __construct(public int $orderId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'workflow.updated';
    }

    public function broadcastWith(): array
    {
         Log::info('OrderWorkflowUpdated broadcastWith', ['orderId' => $this->orderId]);
    return ['orderId' => $this->orderId];
    }
}