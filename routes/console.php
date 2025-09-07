<?php

use Illuminate\Support\Facades\Artisan;
use App\Models\Order;

Artisan::command('orders:delivery:recalc {--dry}', function () {
    $q = Order::where('status', 'completed');
    $total = $q->count();
    $bar = $this->output->createProgressBar($total);
    $bar->start();

    $updated = 0;
    $q->chunkById(200, function ($chunk) use (&$updated, $bar) {
        foreach ($chunk as $o) {
            $from = $o->paid_at ?: $o->created_at;     // старт: paid или created
            $to   = $o->completed_at ?: now();         // финиш: completed или now
            $calc = ($from && $to) ? max(0, $to->getTimestamp() - $from->getTimestamp()) : null;

            if ($o->delivery_seconds !== $calc) {
                if (!$this->option('dry')) {
                    $o->delivery_seconds = $calc;
                    $o->save();
                }
                $updated++;
            }
            $bar->advance();
        }
    });

    $bar->finish();
    $this->newLine(2);
    $this->info("Updated: {$updated}" . ($this->option('dry') ? ' (dry-run)' : ''));
})->describe('Recalculate delivery_seconds for all completed orders.');