<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('option_groups', function (Blueprint $t) {
            // режим композитной формы
            $t->boolean('unique_d4_is_global')->default(false)->after('code');
            $t->foreignId('ga_profile_id')->nullable()->constrained('ga_profiles')->nullOnDelete()->after('unique_d4_is_global');

            // 4 лейбла
            $t->json('unique_d4_labels')->nullable()->after('ga_profile_id');

            // локальные цены (если is_global = false)
            $t->enum('unique_d4_pricing_mode', ['absolute','percent'])->default('absolute')->after('unique_d4_labels');

            // absolute (в центах)
            $t->integer('unique_d4_ga1_cents')->default(0)->after('unique_d4_pricing_mode');
            $t->integer('unique_d4_ga2_cents')->default(0);
            $t->integer('unique_d4_ga3_cents')->default(0);
            $t->integer('unique_d4_ga4_cents')->default(0);

            // percent
            $t->decimal('unique_d4_ga1_percent', 8, 3)->nullable();
            $t->decimal('unique_d4_ga2_percent', 8, 3)->nullable();
            $t->decimal('unique_d4_ga3_percent', 8, 3)->nullable();
            $t->decimal('unique_d4_ga4_percent', 8, 3)->nullable();
        });

        // На всякий: meta в option_values (чтобы хранить ga_count)
        if (Schema::hasTable('option_values') && ! Schema::hasColumn('option_values', 'meta')) {
            Schema::table('option_values', function (Blueprint $t) {
                $t->json('meta')->nullable()->after('delta_percent');
            });
        }
    }

    public function down(): void
    {
        Schema::table('option_groups', function (Blueprint $t) {
            $t->dropForeign(['ga_profile_id']);
            $t->dropColumn([
                'unique_d4_is_global','ga_profile_id','unique_d4_labels','unique_d4_pricing_mode',
                'unique_d4_ga1_cents','unique_d4_ga2_cents','unique_d4_ga3_cents','unique_d4_ga4_cents',
                'unique_d4_ga1_percent','unique_d4_ga2_percent','unique_d4_ga3_percent','unique_d4_ga4_percent',
            ]);
        });
        // meta оставим — она общеполезная
    }
};