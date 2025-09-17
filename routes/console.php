<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

Artisan::command('orders:check-refund-columns', function () {
    $this->info('Checking orders table for refund columns...');

    $added = [];

    // total_refunded_cents
    if (!Schema::hasColumn('orders', 'total_refunded_cents')) {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('total_refunded_cents')->default(0)->after('total_cents');
        });
        $added[] = 'total_refunded_cents';
        $this->warn('Added column: total_refunded_cents (INT, default 0)');
    } else {
        $this->line('✓ Column exists: total_refunded_cents');
    }

    // refunded_at
    if (!Schema::hasColumn('orders', 'refunded_at')) {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
        });
        $added[] = 'refunded_at';
        $this->warn('Added column: refunded_at (TIMESTAMP NULL)');
    } else {
        $this->line('✓ Column exists: refunded_at');
    }

    // Показать небольшую сводку
    try {
        $cnt = DB::table('orders')->count();
        $sumRefunded = DB::table('orders')->sum('total_refunded_cents');
        $this->line("Orders count: {$cnt}");
        $this->line("Sum(total_refunded_cents): " . number_format((int) $sumRefunded));
    } catch (\Throwable $e) {
        $this->warn('Could not read orders summary: ' . $e->getMessage());
    }

    if (empty($added)) {
        $this->info('No changes needed. All columns already exist.');
    } else {
        $this->info('Done. Columns added: ' . implode(', ', $added));
    }
})->describe('Check and add orders.total_refunded_cents and orders.refunded_at if missing.');