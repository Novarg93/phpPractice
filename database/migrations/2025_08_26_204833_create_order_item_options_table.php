<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_item_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('option_value_id')->constrained()->cascadeOnDelete();
            
            $t->string('title');
            $t->integer('price_delta_cents')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_item_options'); }
};