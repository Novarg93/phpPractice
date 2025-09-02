<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('option_values', function (Blueprint $t) {
            $t->json('allow_class_value_ids')->nullable();
            $t->json('allow_slot_value_ids')->nullable();
        });
    }
    public function down(): void {
        Schema::table('option_values', function (Blueprint $t) {
            $t->dropColumn(['allow_class_value_ids','allow_slot_value_ids']);
        });
    }
};