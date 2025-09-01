<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // --- option_groups ---
        if (!Schema::hasColumn('option_groups', 'selection_mode')) {
            Schema::table('option_groups', function (Blueprint $t) {
                $t->string('selection_mode')->nullable(); // single|multi
            });
        }

        if (!Schema::hasColumn('option_groups', 'pricing_mode')) {
            Schema::table('option_groups', function (Blueprint $t) {
                $t->string('pricing_mode')->nullable();   // absolute|percent
            });
        }

        if (!Schema::hasColumn('option_groups', 'multiply_by_qty')) {
            Schema::table('option_groups', function (Blueprint $t) {
                $t->boolean('multiply_by_qty')->default(false);
            });
        }

        // --- option_values ---
        if (!Schema::hasColumn('option_values', 'delta_cents')) {
            Schema::table('option_values', function (Blueprint $t) {
                $t->integer('delta_cents')->nullable();
            });
        }

        if (!Schema::hasColumn('option_values', 'delta_percent')) {
            Schema::table('option_values', function (Blueprint $t) {
                $t->decimal('delta_percent', 8, 2)->nullable();
            });
        }

        // backfill (только если есть исходные колонки)
        if (Schema::hasColumn('option_values', 'price_delta_cents') && Schema::hasColumn('option_values', 'delta_cents')) {
            DB::table('option_values')
                ->whereNotNull('price_delta_cents')
                ->update(['delta_cents' => DB::raw('price_delta_cents')]);
        }

        if (Schema::hasColumn('option_values', 'value_percent') && Schema::hasColumn('option_values', 'delta_percent')) {
            DB::table('option_values')
                ->whereNotNull('value_percent')
                ->update(['delta_percent' => DB::raw('value_percent')]);
        }

        // map legacy -> selector (безопасно, если миграция гоняется повторно)
        if (
            Schema::hasColumn('option_groups', 'type') &&
            Schema::hasColumn('option_groups', 'selection_mode') &&
            Schema::hasColumn('option_groups', 'pricing_mode')
        ) {
            $map = [
                'radio_additive'    => ['single','absolute'],
                'checkbox_additive' => ['multi','absolute'],
                'radio_percent'     => ['single','percent'],
                'checkbox_percent'  => ['multi','percent'],
            ];
            foreach ($map as $legacy => [$sel, $pr]) {
                DB::table('option_groups')
                    ->where('type', $legacy)
                    ->update([
                        'type' => 'selector',
                        'selection_mode' => $sel,
                        'pricing_mode'   => $pr,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // удаляем только существующие — SQLite это любит
        if (Schema::hasColumn('option_values','delta_cents')) {
            Schema::table('option_values', fn (Blueprint $t) => $t->dropColumn('delta_cents'));
        }
        if (Schema::hasColumn('option_values','delta_percent')) {
            Schema::table('option_values', fn (Blueprint $t) => $t->dropColumn('delta_percent'));
        }

        if (Schema::hasColumn('option_groups','selection_mode')) {
            Schema::table('option_groups', fn (Blueprint $t) => $t->dropColumn('selection_mode'));
        }
        if (Schema::hasColumn('option_groups','pricing_mode')) {
            Schema::table('option_groups', fn (Blueprint $t) => $t->dropColumn('pricing_mode'));
        }
        if (Schema::hasColumn('option_groups','multiply_by_qty')) {
            Schema::table('option_groups', fn (Blueprint $t) => $t->dropColumn('multiply_by_qty'));
        }
    }
};