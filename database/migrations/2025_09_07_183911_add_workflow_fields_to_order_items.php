<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            if (!Schema::hasColumn('order_items', 'cost_cents')) {
                $t->unsignedInteger('cost_cents')->nullable()->after('line_total_cents');
            }
            if (!Schema::hasColumn('order_items', 'profit_cents')) {
                $t->integer('profit_cents')->nullable()->after('cost_cents');
            }
            if (!Schema::hasColumn('order_items', 'margin_bp')) {
                $t->integer('margin_bp')->nullable()->after('profit_cents');
            }
            if (!Schema::hasColumn('order_items', 'status')) {
                // item-статус для workflow: paid / in_progress / completed / refund
                $t->string('status', 20)->default('paid')->after('margin_bp');
                $t->index(['order_id', 'status']);
            }
            if (!Schema::hasColumn('order_items', 'link_screen')) {
                $t->string('link_screen', 2048)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            foreach (['cost_cents','profit_cents','margin_bp','status','link_screen'] as $col) {
                if (Schema::hasColumn('order_items', $col)) $t->dropColumn($col);
            }
        });
    }
};