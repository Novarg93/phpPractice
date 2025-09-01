<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'price_preview')) {
            Schema::table('products', function (Blueprint $t) {
                $t->string('price_preview', 255)->nullable()->after('price_cents');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'price_preview')) {
            Schema::table('products', function (Blueprint $t) {
                $t->dropColumn('price_preview');
            });
        }
    }
};