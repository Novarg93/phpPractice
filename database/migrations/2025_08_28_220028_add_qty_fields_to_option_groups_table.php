<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            // НЕ меняем колонку type через ->change(), чтобы не сломать SQLite/без DBAL
            // $table->string('type')->default('radio_additive')->change();

            // просто добавляем qty_* поля
            $table->unsignedInteger('qty_min')->nullable();
            $table->unsignedInteger('qty_max')->nullable();
            $table->unsignedInteger('qty_step')->nullable();
            $table->unsignedInteger('qty_default')->nullable();
        });

        // Если хочешь гарантировать, что type заполнен, сделаем это данными (idempotent):
        DB::table('option_groups')
            ->whereNull('type')
            ->update(['type' => 'radio_additive']);
    }

    public function down(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropColumn(['qty_min', 'qty_max', 'qty_step', 'qty_default']);
        });

        // Откатывать fill для type не обязательно
    }
};