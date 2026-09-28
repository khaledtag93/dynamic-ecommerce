<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->unique(['id', 'order_id'], 'return_requests_id_order_unique');
        });

        Schema::table('return_request_items', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('return_request_id');
        });

        DB::statement(
            'UPDATE return_request_items rri
             JOIN return_requests rr ON rr.id = rri.return_request_id
             SET rri.order_id = rr.order_id'
        );

        Schema::table('return_request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();

            $table->dropForeign('rma_item_request_fk');
            $table->dropForeign('rma_item_order_item_fk');

            $table->foreign(['return_request_id', 'order_id'], 'rma_item_request_order_fk')
                ->references(['id', 'order_id'])
                ->on('return_requests')
                ->cascadeOnDelete();

            $table->foreign(['order_item_id', 'order_id'], 'rma_item_order_item_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_request_items', function (Blueprint $table) {
            $table->dropForeign('rma_item_request_order_fk');
            $table->dropForeign('rma_item_order_item_order_fk');

            $table->foreign('return_request_id', 'rma_item_request_fk')
                ->references('id')
                ->on('return_requests')
                ->cascadeOnDelete();

            $table->foreign('order_item_id', 'rma_item_order_item_fk')
                ->references('id')
                ->on('order_items')
                ->restrictOnDelete();

            $table->dropColumn('order_id');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropUnique('return_requests_id_order_unique');
        });
    }
};
