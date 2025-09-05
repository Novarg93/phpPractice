<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('option_values', function (Blueprint $t) {
            if (! Schema::hasColumn('option_values', 'meta')) {
                // json для MySQL/PG, в SQLite Laravel хранит как TEXT и сам сериализует
                $t->json('meta')->nullable();
            }

            // на всякий случай нормализуем типы (если их ещё нет / nullable нужно)
            if (Schema::hasColumn('option_values', 'delta_cents')) {
                // ok — integer
            } else {
                $t->integer('delta_cents')->nullable();
            }

            if (Schema::hasColumn('option_values', 'delta_percent')) {
                // ok — float/decimal; оставляем как есть
            } else {
                $t->decimal('delta_percent', 8, 3)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('option_values', function (Blueprint $t) {
            if (Schema::hasColumn('option_values', 'meta')) {
                $t->dropColumn('meta');
            }
        });
    }
};