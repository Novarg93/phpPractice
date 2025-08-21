<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('category_product', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_primary')->default(false); // помечаем «главную» связь (опц.)
            $t->unsignedInteger('position')->nullable(); // сортировка внутри категории (опц.)
            $t->unique(['category_id','product_id']);
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('category_product');
    }
};