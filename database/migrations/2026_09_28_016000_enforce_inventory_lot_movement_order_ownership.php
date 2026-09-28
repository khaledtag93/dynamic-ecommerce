<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_stock_reservations', function (Blueprint $table) {
            $table->unique(['id', 'order_id'], 'stock_res_id_order_unique');
        });

        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropForeign(['order_stock_reservation_id']);

            $table->foreign(['order_item_id', 'order_id'], 'lot_movements_item_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_items')
                ->nullOnDelete();

            $table->foreign(['order_stock_reservation_id', 'order_id'], 'lot_movements_reservation_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_stock_reservations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            $table->dropForeign('lot_movements_item_order_fk');
            $table->dropForeign('lot_movements_reservation_order_fk');

            $table->foreign('order_item_id')
                ->references('id')
                ->on('order_items')
                ->nullOnDelete();

            $table->foreign('order_stock_reservation_id')
                ->references('id')
                ->on('order_stock_reservations')
                ->nullOnDelete();
        });

        Schema::table('order_stock_reservations', function (Blueprint $table) {
            $table->dropUnique('stock_res_id_order_unique');
        });
    }
};
