<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            $table->unsignedBigInteger('option_group_id')->nullable()->after('option_value_id');
            $table->integer('selected_min')->nullable()->after('option_group_id');
            $table->integer('selected_max')->nullable()->after('selected_min');
            $table->integer('price_delta_cents')->default(0)->change(); // уже есть — убеждаемся в дефолте
            $table->json('payload_json')->nullable()->after('price_delta_cents');

            $table->foreign('option_group_id')->references('id')->on('option_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            $table->dropForeign(['option_group_id']);
            $table->dropColumn(['option_group_id', 'selected_min', 'selected_max', 'payload_json']);
        });
    }
};