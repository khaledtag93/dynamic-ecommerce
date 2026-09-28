<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->unique(['id', 'purchase_id'], 'purchase_items_id_purchase_unique');
        });

        Schema::table('purchase_receiving_progress', function (Blueprint $table) {
            $table->dropForeign(['purchase_item_id']);
            $table->foreign(['purchase_item_id', 'purchase_id'], 'purchase_progress_item_purchase_fk')
                ->references(['id', 'purchase_id'])
                ->on('purchase_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receiving_progress', function (Blueprint $table) {
            $table->dropForeign('purchase_progress_item_purchase_fk');
            $table->foreign('purchase_item_id')
                ->references('id')
                ->on('purchase_items')
                ->cascadeOnDelete();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropUnique('purchase_items_id_purchase_unique');
        });
    }
};
