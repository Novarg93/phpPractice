<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();        // уникальный код (slug) страницы
            $table->string('name');                  // отображаемое имя
            $table->text('text')->nullable();        // html/markdown (как хранишь — решай сам)
            $table->unsignedInteger('order')->default(0); // порядок в списке

            // SEO
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();

            $table->timestamps();

            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};