<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            // Поле уже есть, просто убеждаемся в дефолте
            $table->integer('price_delta_cents')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            // Никакие поля не удаляем — только восстанавливаем состояние если нужно
            // можно оставить пустым, чтобы не трогать существующие данные
        });
    }
};
