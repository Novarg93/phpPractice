<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('promo_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $t->integer('amount_cents'); // фактическая скидка в центах
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('promo_redemptions');
    }
};