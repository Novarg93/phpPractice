<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'total_cost_cents')) {
                $t->unsignedBigInteger('total_cost_cents')->nullable()->after('total_cents');
            }
            if (!Schema::hasColumn('orders', 'total_profit_cents')) {
                $t->bigInteger('total_profit_cents')->nullable()->after('total_cost_cents');
            }
            if (!Schema::hasColumn('orders', 'margin_bp')) {
                // margin в базисных пунктах: 1% = 100
                $t->integer('margin_bp')->nullable()->after('total_profit_cents');
            }
            if (!Schema::hasColumn('orders', 'refund_total_cents')) {
                $t->unsignedBigInteger('refund_total_cents')->default(0)->after('margin_bp');
            }
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $t->timestamp('completed_at')->nullable()->after('placed_at');
            }
            if (!Schema::hasColumn('orders', 'refunded_at')) {
                $t->timestamp('refunded_at')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            foreach (['total_cost_cents','total_profit_cents','margin_bp','refund_total_cents','completed_at','refunded_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) $t->dropColumn($col);
            }
        });
    }
};