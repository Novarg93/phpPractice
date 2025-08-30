<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            $table->unsignedBigInteger('option_value_id')->nullable()->change();
        });

        Schema::table('order_item_options', function (Blueprint $table) {
            $table->unsignedBigInteger('option_value_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            $table->unsignedBigInteger('option_value_id')->nullable(false)->change();
        });

        Schema::table('order_item_options', function (Blueprint $table) {
            $table->unsignedBigInteger('option_value_id')->nullable(false)->change();
        });
    }
};