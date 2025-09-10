<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('promo_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();                    // сам код (ABC123)
            $t->enum('type', ['percent', 'amount']);         // percent | amount
            $t->unsignedInteger('value_percent')->nullable();// для percent (1..100)
            $t->integer('value_cents')->nullable();          // для amount (в центах)
            $t->unsignedBigInteger('min_order_cents')->nullable();    // мин. заказ
            $t->unsignedBigInteger('max_discount_cents')->nullable(); // «потолок» скидки
            $t->unsignedInteger('max_uses')->nullable();              // лимит глобально
            $t->unsignedInteger('per_user_max_uses')->nullable();     // лимит на юзера
            $t->unsignedInteger('uses_count')->default(0);            // использований
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);

            // опционально — кешируем соответствующий Stripe coupon (чтобы не создавать каждый раз)
            $t->string('stripe_coupon_id')->nullable();
            $t->string('stripe_coupon_currency', 3)->nullable(); // 'usd' и т.п.

            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('promo_codes');
    }
};
