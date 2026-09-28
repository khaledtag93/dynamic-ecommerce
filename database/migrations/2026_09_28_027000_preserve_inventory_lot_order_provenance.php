<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign('lot_movements_item_order_fk');
            $table->dropForeign('lot_movements_reservation_order_fk');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->restrictOnDelete();

            $table->foreign(['order_item_id', 'order_id'], 'lot_movements_item_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_items')
                ->restrictOnDelete();

            $table->foreign(['order_stock_reservation_id', 'order_id'], 'lot_movements_reservation_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_stock_reservations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign('lot_movements_item_order_fk');
            $table->dropForeign('lot_movements_reservation_order_fk');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();

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
};
