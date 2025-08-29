<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->unsignedInteger('slider_min')->nullable();
            $table->unsignedInteger('slider_max')->nullable();
            $table->unsignedInteger('slider_step')->nullable();
            $table->unsignedInteger('slider_default')->nullable();
        });
    }

    public function down(): void {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropColumn(['slider_min','slider_max','slider_step','slider_default']);
        });
    }
};