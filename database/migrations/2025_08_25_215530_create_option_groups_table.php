<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // radio_additive | checkbox_additive
            $table->string('type');
            $table->boolean('is_required')->default(false); // для радио часто true
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('option_groups'); }
};
