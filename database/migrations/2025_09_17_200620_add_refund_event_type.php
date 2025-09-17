<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $t) {
            if (!Schema::hasColumn('refunds', 'event_type')) {
                $t->string('event_type')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $t) {
            if (Schema::hasColumn('refunds', 'event_type')) {
                $t->dropColumn('event_type');
            }
        });
    }
};