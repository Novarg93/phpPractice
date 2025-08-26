<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('paid'); // draft|pending|paid|failed|cancelled
            $t->string('currency', 3)->default('USD');

            $t->integer('subtotal_cents')->default(0);
            $t->integer('shipping_cents')->default(0);
            $t->integer('tax_cents')->default(0);
            $t->integer('total_cents')->default(0);

            $t->string('payment_method')->nullable(); // 'stripe_test'
            $t->string('payment_id')->nullable();     // фейковый id
            $t->timestamp('placed_at')->nullable();

            $t->json('shipping_address')->nullable();
            $t->json('billing_address')->nullable();
            $t->text('notes')->nullable();

            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};