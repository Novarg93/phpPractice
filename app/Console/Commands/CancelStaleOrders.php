<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;

class CancelStaleOrders extends Command
{
    protected $signature = 'orders:cancel-stale';
    protected $description = 'Cancel pending orders older than 24 hours';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subHours();

        $count = Order::where('status', Order::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => Order::STATUS_CANCELED]);

        $this->info("Canceled {$count} stale pending orders.");

        return self::SUCCESS;
    }
}