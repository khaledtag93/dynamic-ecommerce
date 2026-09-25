<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->foreignId('return_request_id')->nullable()->after('order_id');

            $table->foreign('return_request_id', 'refund_rma_fk')
                ->references('id')->on('return_requests')->nullOnDelete();

            $table->index('return_request_id', 'refund_rma_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropForeign('refund_rma_fk');
            $table->dropIndex('refund_rma_idx');
            $table->dropColumn('return_request_id');
        });
    }
};
