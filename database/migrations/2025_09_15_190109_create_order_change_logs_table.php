<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_change_logs', function (Blueprint $table) {
            $table->id();

            // Привязки
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // Что произошло
            $table->string('action', 50);   // e.g. cost_updated, refund_created, status_changed
            $table->string('field', 50)->nullable(); // e.g. cost_cents, status
            $table->bigInteger('old_cents')->nullable();
            $table->bigInteger('new_cents')->nullable();
            $table->text('old_value')->nullable();   // для строковых статусов и т.п.
            $table->text('new_value')->nullable();
            $table->text('note')->nullable();

            $table->json('meta')->nullable(); // запасной карман: IP, UI-контекст, и т.д.
            $table->timestamps();

            $table->index(['order_id', 'order_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_change_logs');
    }
};
