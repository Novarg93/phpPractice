<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Discord
            if (!Schema::hasColumn('users', 'discord_user_id')) {
                $t->string('discord_user_id', 32)->nullable()->unique()->after('avatar');
            }
            if (!Schema::hasColumn('users', 'discord_username')) {
                $t->string('discord_username', 64)->nullable()->after('discord_user_id');
            }
            if (!Schema::hasColumn('users', 'discord_avatar')) {
                $t->string('discord_avatar', 128)->nullable()->after('discord_username');
            }

            // Telegram
            if (!Schema::hasColumn('users', 'telegram_user_id')) {
                $t->string('telegram_user_id', 32)->nullable()->unique()->after('discord_avatar');
            }
            if (!Schema::hasColumn('users', 'telegram_username')) {
                $t->string('telegram_username', 64)->nullable()->after('telegram_user_id');
            }
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $t->string('telegram_chat_id', 32)->nullable()->unique()->after('telegram_username');
            }
            if (!Schema::hasColumn('users', 'telegram_link_code_hash')) {
                $t->string('telegram_link_code_hash', 128)->nullable()->after('telegram_chat_id');
            }
            if (!Schema::hasColumn('users', 'telegram_link_expires_at')) {
                $t->timestamp('telegram_link_expires_at')->nullable()->after('telegram_link_code_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'discord_user_id', 'discord_username', 'discord_avatar',
                'telegram_user_id','telegram_username','telegram_chat_id',
                'telegram_link_code_hash','telegram_link_expires_at',
            ]);
        });
    }
};