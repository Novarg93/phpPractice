<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->boolean('multiply_by_qty')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropColumn('multiply_by_qty');
        });
    }
};
