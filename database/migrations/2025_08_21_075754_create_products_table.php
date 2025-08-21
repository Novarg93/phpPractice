<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 120);
            $table->string('sku')->nullable();
            $table->unsignedInteger('price_cents');
            $table->boolean('is_active')->default(true);

            $table->boolean('track_inventory')->default(false); // по умолчанию анлимитед
            $table->unsignedInteger('stock')->nullable();  // если нужно

            $table->string('image')->nullable();
            $table->text('short')->nullable();
            $table->longText('description')->nullable();
            $table->json('meta')->nullable(); // гибкие поля, например rarity, ilvl и т.д.
            $table->timestamps();

            $table->unique(['category_id', 'slug']); // уникальность в пределах категории
            $table->index(['category_id', 'is_active', 'price_cents']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
