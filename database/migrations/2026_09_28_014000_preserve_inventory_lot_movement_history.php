<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            $table->dropForeign(['inventory_lot_id']);
            $table->dropForeign(['inventory_movement_id']);

            $table->foreign('inventory_lot_id')
                ->references('id')
                ->on('inventory_lots')
                ->restrictOnDelete();

            $table->foreign('inventory_movement_id')
                ->references('id')
                ->on('inventory_movements')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            $table->dropForeign(['inventory_lot_id']);
            $table->dropForeign(['inventory_movement_id']);

            $table->foreign('inventory_lot_id')
                ->references('id')
                ->on('inventory_lots')
                ->cascadeOnDelete();

            $table->foreign('inventory_movement_id')
                ->references('id')
                ->on('inventory_movements')
                ->cascadeOnDelete();
        });
    }
};
