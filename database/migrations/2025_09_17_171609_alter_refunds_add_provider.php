<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('refunds', function (Blueprint $t) {
            if (!Schema::hasColumn('refunds', 'provider')) {
                $t->string('provider', 40)->default('stripe_test')->after('amount_cents');
            }
            if (!Schema::hasColumn('refunds', 'provider_id')) {
                $t->string('provider_id', 100)->nullable()->after('provider');
            }
            if (!Schema::hasColumn('refunds', 'provider_payload')) {
                $t->json('provider_payload')->nullable()->after('provider_id');
            }
        });
    }

    public function down(): void {
        Schema::table('refunds', function (Blueprint $t) {
            foreach (['provider_payload','provider_id','provider'] as $col) {
                if (Schema::hasColumn('refunds', $col)) $t->dropColumn($col);
            }
        });
    }
};