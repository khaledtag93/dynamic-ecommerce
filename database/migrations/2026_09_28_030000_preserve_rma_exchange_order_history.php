<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropForeign('rma_exchange_order_fk');

            $table->foreign('exchange_order_id', 'rma_exchange_order_fk')
                ->references('id')
                ->on('orders')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropForeign('rma_exchange_order_fk');

            $table->foreign('exchange_order_id', 'rma_exchange_order_fk')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
        });
    }
};
