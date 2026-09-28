<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->foreign('purchase_id')->references('id')->on('purchases')->restrictOnDelete();
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->foreign('purchase_id')->references('id')->on('purchases')->restrictOnDelete();
        });

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_receipt_id']);
            $table->dropForeign(['purchase_item_id']);
            $table->foreign('purchase_receipt_id')->references('id')->on('purchase_receipts')->restrictOnDelete();
            $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_receipt_id']);
            $table->dropForeign(['purchase_item_id']);
            $table->foreign('purchase_receipt_id')->references('id')->on('purchase_receipts')->cascadeOnDelete();
            $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->cascadeOnDelete();
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });
    }
};
