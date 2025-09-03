<?php

namespace App\Livewire\Admin;

use App\Models\ContactMessage;
use App\Models\Order;
use Livewire\Component;

class RealtimeIndicator extends Component
{
    public int $messages = 0;
    public int $orders = 0;

    public function mount(): void
    {
        $this->refreshCounts();
    }

    public function refreshCounts(): void
    {
        $this->messages = ContactMessage::where('status', 'new')->count();
        $this->orders = class_exists(\App\Models\Order::class)
            ? \App\Models\Order::where('status', 'new')->count()
            : 0;
    }

    public function render()
    {
        return view('livewire.admin.realtime-indicator');
    }
}