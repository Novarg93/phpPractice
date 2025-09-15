<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'total_refunded_cents')) {
                $t->integer('total_refunded_cents')->default(0)->after('total_cents');
            }
            if (!Schema::hasColumn('orders', 'refunded_at')) {
                $t->timestamp('refunded_at')->nullable()->after('completed_at');
            }
        });

        Schema::table('order_items', function (Blueprint $t) {
            if (!Schema::hasColumn('order_items', 'refunded_qty')) {
                // DECIMAL на будущее (если вдруг появятся доли)
                $t->decimal('refunded_qty', 10, 2)->default(0)->after('qty');
            }
            if (!Schema::hasColumn('order_items', 'refunded_amount_cents')) {
                $t->integer('refunded_amount_cents')->default(0)->after('line_total_cents');
            }
        });
    }

    public function down(): void {
        Schema::table('orders', function (Blueprint $t) {
            foreach (['total_refunded_cents','refunded_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) $t->dropColumn($col);
            }
        });
        Schema::table('order_items', function (Blueprint $t) {
            foreach (['refunded_qty','refunded_amount_cents'] as $col) {
                if (Schema::hasColumn('order_items', $col)) $t->dropColumn($col);
            }
        });
    }
};