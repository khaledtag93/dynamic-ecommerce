<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropForeign(['purchase_receipt_id']);
            $table->dropForeign(['purchase_item_id']);

            $table->foreign(['purchase_receipt_id', 'purchase_id'], 'inventory_lots_receipt_purchase_fk')
                ->references(['id', 'purchase_id'])
                ->on('purchase_receipts')
                ->restrictOnDelete();

            $table->foreign(['purchase_item_id', 'purchase_id'], 'inventory_lots_item_purchase_fk')
                ->references(['id', 'purchase_id'])
                ->on('purchase_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropForeign('inventory_lots_receipt_purchase_fk');
            $table->dropForeign('inventory_lots_item_purchase_fk');

            $table->foreign('purchase_receipt_id')
                ->references('id')
                ->on('purchase_receipts')
                ->nullOnDelete();

            $table->foreign('purchase_item_id')
                ->references('id')
                ->on('purchase_items')
                ->nullOnDelete();
        });
    }
};
