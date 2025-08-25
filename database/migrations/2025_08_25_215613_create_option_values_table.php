<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->integer('price_delta_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // ← сразу тут
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('option_values');
    }
};