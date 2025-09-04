<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cart_item_options') && Schema::hasColumn('cart_item_options', 'is_ga')) {
            Schema::table('cart_item_options', function (Blueprint $t) {
                // имя индекса задаём явно, чтобы корректно дропнуть в down()
                $t->index('is_ga', 'cart_item_options_is_ga_index');
            });
        }

        if (Schema::hasTable('order_item_options') && Schema::hasColumn('order_item_options', 'is_ga')) {
            Schema::table('order_item_options', function (Blueprint $t) {
                $t->index('is_ga', 'order_item_options_is_ga_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cart_item_options')) {
            Schema::table('cart_item_options', function (Blueprint $t) {
                $t->dropIndex('cart_item_options_is_ga_index');
                // или: $t->dropIndex(['is_ga']);
            });
        }

        if (Schema::hasTable('order_item_options')) {
            Schema::table('order_item_options', function (Blueprint $t) {
                $t->dropIndex('order_item_options_is_ga_index');
            });
        }
    }
};