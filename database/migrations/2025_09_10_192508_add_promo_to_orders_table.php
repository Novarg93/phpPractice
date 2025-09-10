<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $t->integer('promo_discount_cents')->default(0);
        });
    }
    public function down(): void {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('promo_code_id');
            $t->dropColumn('promo_discount_cents');
        });
    }
};