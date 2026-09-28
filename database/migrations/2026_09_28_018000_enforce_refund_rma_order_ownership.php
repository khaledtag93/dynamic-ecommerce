<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropForeign('refund_rma_fk');

            $table->foreign(['return_request_id', 'order_id'], 'refund_rma_order_fk')
                ->references(['id', 'order_id'])
                ->on('return_requests')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropForeign('refund_rma_order_fk');

            $table->foreign('return_request_id', 'refund_rma_fk')
                ->references('id')
                ->on('return_requests')
                ->nullOnDelete();
        });
    }
};
