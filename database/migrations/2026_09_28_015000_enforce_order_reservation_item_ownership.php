<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unique(['id', 'order_id'], 'order_items_id_order_unique');
        });

        Schema::table('order_stock_reservations', function (Blueprint $table) {
            $table->dropForeign('stock_res_item_fk');

            $table->foreign(['order_item_id', 'order_id'], 'stock_res_item_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_stock_reservations', function (Blueprint $table) {
            $table->dropForeign('stock_res_item_order_fk');

            $table->foreign('order_item_id', 'stock_res_item_fk')
                ->references('id')
                ->on('order_items')
                ->cascadeOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('order_items_id_order_unique');
        });
    }
};
