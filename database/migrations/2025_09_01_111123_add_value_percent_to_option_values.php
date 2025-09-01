<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('option_values', function (Blueprint $table) {
            $table->decimal('value_percent', 8, 3)->nullable()->after('price_delta_cents');
        });
    }
    public function down(): void {
        Schema::table('option_values', function (Blueprint $table) {
            $table->dropColumn('value_percent');
        });
    }
};