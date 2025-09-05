<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ga_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();          // например: 'unique_d4_default'
            $t->string('title')->nullable();      // читаемое название профиля
            $t->enum('pricing_mode', ['absolute','percent'])->default('absolute');

            // absolute (в центах)
            $t->integer('ga1_cents')->default(0);
            $t->integer('ga2_cents')->default(0);
            $t->integer('ga3_cents')->default(0);
            $t->integer('ga4_cents')->default(0);

            // percent (в процентах, до 3 знаков)
            $t->decimal('ga1_percent', 8, 3)->nullable();
            $t->decimal('ga2_percent', 8, 3)->nullable();
            $t->decimal('ga3_percent', 8, 3)->nullable();
            $t->decimal('ga4_percent', 8, 3)->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ga_profiles');
    }
};