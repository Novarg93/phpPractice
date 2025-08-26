<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();

            // снапшоты на момент покупки
            $t->string('product_name');
            $t->integer('unit_price_cents');
            $t->unsignedInteger('qty');
            $t->integer('line_total_cents');

            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_items'); }
};