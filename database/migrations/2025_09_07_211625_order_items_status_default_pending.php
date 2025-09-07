<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Пытаемся поменять дефолт там, где это реально работает
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'pgsql', 'sqlsrv'], true)) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('status', 32)->default('pending')->change();
            });
        } else {
            // SQLite: оставляем дефолт на уровне приложения (как ты уже делаешь при create),
            // чтобы не устраивать пересборку таблицы.
            // Можно оставить комментарий для истории:
            DB::unprepared("-- SQLite: default оставлен как есть; используется app-level default 'pending'");
        }

        // 2) БЭКФИЛЛ: если заказ pending, а айтемы = paid → вернуть их в pending
        DB::statement("
            UPDATE order_items
            SET status = 'pending'
            WHERE status = 'paid'
              AND order_id IN (SELECT id FROM orders WHERE status = 'pending')
        ");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'pgsql', 'sqlsrv'], true)) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('status', 32)->default('paid')->change();
            });
        } else {
            DB::unprepared("-- SQLite: откат default пропущен");
        }
    }
};