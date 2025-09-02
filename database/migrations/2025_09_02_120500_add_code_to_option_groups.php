<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('option_groups', function (Blueprint $t) {
            $t->string('code')->nullable()->index(); // например: class, slot, affix
        });
    }
    public function down(): void {
        Schema::table('option_groups', function (Blueprint $t) {
            $t->dropColumn('code');
        });
    }
};