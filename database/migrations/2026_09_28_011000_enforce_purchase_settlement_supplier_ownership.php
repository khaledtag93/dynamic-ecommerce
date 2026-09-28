<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->unique(['id', 'supplier_id'], 'purchases_id_supplier_unique');
        });

        Schema::table('purchase_settlements', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->dropForeign(['supplier_id']);

            $table->foreign(['purchase_id', 'supplier_id'], 'purchase_settlements_purchase_supplier_fk')
                ->references(['id', 'supplier_id'])
                ->on('purchases')
                ->restrictOnDelete();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_settlements', function (Blueprint $table) {
            $table->dropForeign('purchase_settlements_purchase_supplier_fk');
            $table->dropForeign(['supplier_id']);

            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->restrictOnDelete();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->restrictOnDelete();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique('purchases_id_supplier_unique');
        });
    }
};
