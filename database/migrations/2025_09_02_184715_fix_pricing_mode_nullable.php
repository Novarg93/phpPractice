<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Для SQLite Laravel сам пересоберёт таблицу (create temp -> copy).
        Schema::table('option_groups', function (Blueprint $t) {
            // Меняем колонку на nullable без дефолта
            $t->string('pricing_mode')->nullable()->change();
        });

        // Бэкофис: всё, что было 'flat' у selector — считаем 'absolute'
        DB::table('option_groups')
            ->where('type', 'selector')
            ->where('pricing_mode', 'flat')
            ->update(['pricing_mode' => 'absolute']);
    }

    public function down(): void
    {
        // В даун можно вернуть not null + дефолт 'flat' (если нужно)
        Schema::table('option_groups', function (Blueprint $t) {
            $t->string('pricing_mode')->default('flat')->nullable(false)->change();
        });
    }
};