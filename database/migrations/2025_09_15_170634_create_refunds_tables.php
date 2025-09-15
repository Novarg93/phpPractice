<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();

            $t->integer('amount_cents');           // общая сумма рефанда (в центах)
            $t->string('status')->default('pending'); // pending|succeeded|failed
            $t->string('reason')->nullable();
            $t->json('meta')->nullable();          // на будущее
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // админ/саппорт

            $t->timestamps();
        });

        Schema::create('refund_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            $t->decimal('qty', 10, 2)->nullable();       // можно не указывать, если "по сумме"
            $t->integer('amount_cents');                 // сумма, относящаяся к этой строке
            $t->string('note')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
    }
};