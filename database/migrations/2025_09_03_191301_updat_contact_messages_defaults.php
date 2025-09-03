<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->enum('status', ['new','in_progress','done'])
                  ->default('new')
                  ->change();
        });

        // На всякий: заполнить пустые статусы
        DB::table('contact_messages')
            ->whereNull('status')
            ->update(['status' => 'new']);
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->enum('status', ['new','in_progress','done'])
                  ->default(null)
                  ->change();
        });
    }
};