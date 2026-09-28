<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);

            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);

            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->nullOnDelete();
        });
    }
};
