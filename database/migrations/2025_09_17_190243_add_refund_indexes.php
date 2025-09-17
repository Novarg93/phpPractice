<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // если столбца ещё нет, пропусти/скорректируй
            if (!Schema::hasColumn('orders', 'payment_id')) {
                $table->string('payment_id')->nullable()->index();
            } else {
                $table->index('payment_id');
            }
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->index('order_id');
            // provider_id должен быть уникальным для идемпотентности
            $table->unique('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropUnique(['provider_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_id']);
        });
    }
};