<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            // 1) option_value_id должен быть nullable (для range-строк он будет NULL)
            //    Сначала дропаем FK, потом меняем колонку, потом вешаем FK заново.
            if (Schema::hasColumn('cart_item_options', 'option_value_id')) {
                $table->dropForeign(['option_value_id']);
            }

            // NB: для change() в MySQL нужен doctrine/dbal; если его нет — смотри комментарий ниже (*)
            $table->unsignedBigInteger('option_value_id')->nullable()->change();

            // FK обратно, разрешаем NULL (он и так разрешён), при удалении значения — NULL
            $table->foreign('option_value_id')
                ->references('id')->on('option_values')
                ->nullOnDelete();
        });

        Schema::table('cart_item_options', function (Blueprint $table) {
            // 2) поля для double range
            if (!Schema::hasColumn('cart_item_options', 'option_group_id')) {
                $table->unsignedBigInteger('option_group_id')->nullable()->after('option_value_id');
                // FK на option_groups, NULL допустим
                $table->foreign('option_group_id')
                    ->references('id')->on('option_groups')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('cart_item_options', 'selected_min')) {
                $table->integer('selected_min')->nullable()->after('option_group_id');
            }
            if (!Schema::hasColumn('cart_item_options', 'selected_max')) {
                $table->integer('selected_max')->nullable()->after('selected_min');
            }
            if (!Schema::hasColumn('cart_item_options', 'price_delta_cents')) {
                $table->integer('price_delta_cents')->nullable()->after('selected_max');
            }
            if (!Schema::hasColumn('cart_item_options', 'payload_json')) {
                // В MySQL это JSON, в SQLite — TEXT под капотом, ок.
                $table->json('payload_json')->nullable()->after('price_delta_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_item_options', function (Blueprint $table) {
            // откатываем добавленные поля
            if (Schema::hasColumn('cart_item_options', 'payload_json')) {
                $table->dropColumn('payload_json');
            }
            if (Schema::hasColumn('cart_item_options', 'price_delta_cents')) {
                $table->dropColumn('price_delta_cents');
            }
            if (Schema::hasColumn('cart_item_options', 'selected_max')) {
                $table->dropColumn('selected_max');
            }
            if (Schema::hasColumn('cart_item_options', 'selected_min')) {
                $table->dropColumn('selected_min');
            }
            if (Schema::hasColumn('cart_item_options', 'option_group_id')) {
                $table->dropForeign(['option_group_id']);
                $table->dropColumn('option_group_id');
            }

            // возвращаем обязательность option_value_id (как было)
            if (Schema::hasColumn('cart_item_options', 'option_value_id')) {
                $table->dropForeign(['option_value_id']);
                // ВНИМАНИЕ: для change() нужен doctrine/dbal
                $table->unsignedBigInteger('option_value_id')->nullable(false)->change();
                $table->foreign('option_value_id')
                    ->references('id')->on('option_values')
                    ->cascadeOnDelete();
            }
        });
    }
};