<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $t->timestamp('paid_at')->nullable()->after('placed_at');
            }
            if (!Schema::hasColumn('orders', 'delivery_seconds')) {
                $t->unsignedInteger('delivery_seconds')->nullable()->after('refunded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (Schema::hasColumn('orders', 'paid_at')) $t->dropColumn('paid_at');
            if (Schema::hasColumn('orders', 'delivery_seconds')) $t->dropColumn('delivery_seconds');
        });
    }
};