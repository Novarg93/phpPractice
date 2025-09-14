<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('google_user_id')->nullable()->unique();
            $t->string('google_email')->nullable()->index();
            $t->string('google_avatar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['google_user_id', 'google_email', 'google_avatar']);
        });
    }
};