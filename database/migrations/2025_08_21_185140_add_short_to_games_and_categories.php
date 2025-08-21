<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class {
    public function up(): void {
        Schema::table('games', fn($t) => $t->text('short')->nullable()->after('description'));
        Schema::table('categories', fn($t) => $t->text('short')->nullable()->after('description'));
    }
    public function down(): void {
        Schema::table('games', fn($t) => $t->dropColumn('short'));
        Schema::table('categories', fn($t) => $t->dropColumn('short'));
    }
};
