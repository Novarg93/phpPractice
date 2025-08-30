<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            // диапазон
            $table->integer('range_default_min')->nullable()->after('slider_step');
            $table->integer('range_default_max')->nullable()->after('range_default_min');

            // ценообразование
            $table->string('pricing_mode')->default('flat')->after('range_default_max'); // flat|tiered
            $table->integer('unit_price_cents')->nullable()->after('pricing_mode');      // для flat

            $table->string('tier_combine_strategy')->nullable()->after('unit_price_cents'); // sum_piecewise|highest_tier_only|weighted_average
            $table->json('tiers_json')->nullable()->after('tier_combine_strategy');         // массив ступеней

            // доп-настройки
            $table->integer('base_fee_cents')->nullable()->default(0)->after('tiers_json');
            $table->integer('max_span')->nullable()->after('base_fee_cents');
            $table->string('rounding')->nullable()->after('max_span');  // например: ceil_to_int, round_2dp
            $table->string('currency', 8)->nullable()->after('rounding')->default('USD');
        });
    }

    public function down(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropColumn([
                'range_default_min',
                'range_default_max',
                'pricing_mode',
                'unit_price_cents',
                'tier_combine_strategy',
                'tiers_json',
                'base_fee_cents',
                'max_span',
                'rounding',
                'currency',
            ]);
        });
    }
};