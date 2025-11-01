<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            // Индекс для фильтра по статусу + курсорной сортировки
            $t->index(['status', 'order_id', 'id'], 'oi_status_order_id_id_idx');

            // Индекс для курсора без статуса
            $t->index(['order_id', 'id'], 'oi_order_id_id_idx');
        });

        // Индекс по ссылке (link_screen) с ограничением длины, чтобы избежать ошибки 1071
        DB::statement('CREATE INDEX oi_link_screen_idx ON order_items (link_screen(100))');

        Schema::table('orders', function (Blueprint $t) {
            // здесь нет индексов — можно оставить пустым или добавить по необходимости
        });

        Schema::table('order_item_options', function (Blueprint $t) {
            // Индекс для названия опции
            $t->index(['order_item_id', 'title'], 'oio_item_title_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropIndex('oi_status_order_id_id_idx');
            $t->dropIndex('oi_order_id_id_idx');
        });

        // удаляем индекс, созданный вручную
        DB::statement('DROP INDEX IF EXISTS oi_link_screen_idx ON order_items');

        Schema::table('order_item_options', function (Blueprint $t) {
            $t->dropIndex('oio_item_title_idx');
        });
    }
};

