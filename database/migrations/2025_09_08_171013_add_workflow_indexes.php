<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            // для фильтра по статусу + курсорной сортировки
            $t->index(['status', 'order_id', 'id'], 'oi_status_order_id_id_idx');
            // для курсора без статуса (если понадобится)
            $t->index(['order_id', 'id'], 'oi_order_id_id_idx');
            // быстрый поиск по ссылке
            $t->index('link_screen', 'oi_link_screen_idx');
        });
        Schema::table('orders', function (Blueprint $t) {
            // если используешь поиск по nickname/character часто (MySQL):
            // можно завести сгенерированные столбцы и повесить индексы.
            // В SQLite индексы по JSON не делаем.
        });
        Schema::table('order_item_options', function (Blueprint $t) {
            // название опции
            $t->index(['order_item_id', 'title'], 'oio_item_title_idx');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->index('email', 'users_email_idx');
            $t->index('name', 'users_name_idx');
            $t->index('full_name', 'users_full_name_idx');
        });
    }
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropIndex('oi_status_order_id_id_idx');
            $t->dropIndex('oi_order_id_id_idx');
            $t->dropIndex('oi_link_screen_idx');
        });
        Schema::table('order_item_options', function (Blueprint $t) {
            $t->dropIndex('oio_item_title_idx');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex('users_email_idx');
            $t->dropIndex('users_name_idx');
            $t->dropIndex('users_full_name_idx');
        });
    }
};