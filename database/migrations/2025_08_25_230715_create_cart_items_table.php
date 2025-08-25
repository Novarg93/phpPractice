<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->integer('unit_price_cents'); // цена с учётом опций
            $table->integer('line_total_cents'); // unit_price * qty
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('cart_items');
    }
};
