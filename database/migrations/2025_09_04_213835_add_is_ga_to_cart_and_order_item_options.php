<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cart_item_options') && ! Schema::hasColumn('cart_item_options', 'is_ga')) {
            Schema::table('cart_item_options', function (Blueprint $t) {
                $t->boolean('is_ga')->default(false)->after('option_value_id');
            });
        }

        if (Schema::hasTable('order_item_options') && ! Schema::hasColumn('order_item_options', 'is_ga')) {
            Schema::table('order_item_options', function (Blueprint $t) {
                $t->boolean('is_ga')->default(false)->after('option_value_id');
            });
        }
        
    }

    public function down(): void
    {
        if (Schema::hasTable('cart_item_options') && Schema::hasColumn('cart_item_options', 'is_ga')) {
            Schema::table('cart_item_options', function (Blueprint $t) {
                $t->dropColumn('is_ga');
            });
        }

        if (Schema::hasTable('order_item_options') && Schema::hasColumn('order_item_options', 'is_ga')) {
            Schema::table('order_item_options', function (Blueprint $t) {
                $t->dropColumn('is_ga');
            });
        }
    }
};