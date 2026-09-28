<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->unique(['id', 'order_id'], 'order_refunds_id_order_unique');
        });

        Schema::table('pos_return_items', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('order_refund_id');
        });

        DB::statement(
            'UPDATE pos_return_items pri
             JOIN order_refunds r ON r.id = pri.order_refund_id
             SET pri.order_id = r.order_id'
        );

        Schema::table('pos_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();

            $table->dropForeign(['order_refund_id']);
            $table->dropForeign(['order_item_id']);

            $table->foreign(['order_refund_id', 'order_id'], 'pos_return_refund_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_refunds')
                ->restrictOnDelete();

            $table->foreign(['order_item_id', 'order_id'], 'pos_return_item_order_fk')
                ->references(['id', 'order_id'])
                ->on('order_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_return_items', function (Blueprint $table) {
            $table->dropForeign('pos_return_refund_order_fk');
            $table->dropForeign('pos_return_item_order_fk');

            $table->foreign('order_refund_id')
                ->references('id')
                ->on('order_refunds')
                ->cascadeOnDelete();

            $table->foreign('order_item_id')
                ->references('id')
                ->on('order_items')
                ->cascadeOnDelete();

            $table->dropColumn('order_id');
        });

        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropUnique('order_refunds_id_order_unique');
        });
    }
};
