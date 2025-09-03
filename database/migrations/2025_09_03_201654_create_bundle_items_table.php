<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bundle_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();

            // опциональные оверрайды qty, если нужно «перебить» настройки базы
            $t->unsignedInteger('qty_min')->nullable();
            $t->unsignedInteger('qty_max')->nullable();
            $t->unsignedInteger('qty_step')->nullable();
            $t->unsignedInteger('qty_default')->nullable();

            $t->unsignedInteger('position')->default(0);
            $t->timestamps();

            $t->unique(['option_group_id', 'product_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('bundle_items');
    }
};