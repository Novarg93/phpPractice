<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'telegram_photo_url')) {
                $t->string('telegram_photo_url', 255)->nullable()->after('telegram_username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'telegram_photo_url')) {
                $t->dropColumn('telegram_photo_url');
            }
        });
    }
};