<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('qty_min')->nullable();
            $table->unsignedInteger('qty_max')->nullable();
            $table->unsignedInteger('qty_step')->nullable();
            $table->unsignedInteger('qty_default')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['qty_min', 'qty_max', 'qty_step', 'qty_default']);
        });
    }
};
