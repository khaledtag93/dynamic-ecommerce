<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->unique(['id', 'purchase_id'], 'purchase_receipts_id_purchase_unique');
        });

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->after('purchase_receipt_id');
        });

        DB::statement(
            'UPDATE purchase_receipt_items pri
             JOIN purchase_receipts pr ON pr.id = pri.purchase_receipt_id
             SET pri.purchase_id = pr.purchase_id'
        );

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_id')->nullable(false)->change();

            $table->dropForeign(['purchase_receipt_id']);
            $table->dropForeign(['purchase_item_id']);

            $table->foreign(['purchase_receipt_id', 'purchase_id'], 'purchase_receipt_items_receipt_purchase_fk')
                ->references(['id', 'purchase_id'])
                ->on('purchase_receipts')
                ->restrictOnDelete();

            $table->foreign(['purchase_item_id', 'purchase_id'], 'purchase_receipt_items_item_purchase_fk')
                ->references(['id', 'purchase_id'])
                ->on('purchase_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropForeign('purchase_receipt_items_receipt_purchase_fk');
            $table->dropForeign('purchase_receipt_items_item_purchase_fk');

            $table->foreign('purchase_receipt_id')
                ->references('id')
                ->on('purchase_receipts')
                ->restrictOnDelete();

            $table->foreign('purchase_item_id')
                ->references('id')
                ->on('purchase_items')
                ->restrictOnDelete();

            $table->dropColumn('purchase_id');
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropUnique('purchase_receipts_id_purchase_unique');
        });
    }
};
