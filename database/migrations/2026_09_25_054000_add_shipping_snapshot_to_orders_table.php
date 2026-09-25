<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')->nullable()->after('delivery_method');
            $table->foreignId('shipping_zone_id')->nullable()->after('shipping_method_id');
            $table->foreignId('shipping_rate_id')->nullable()->after('shipping_zone_id');
            $table->json('shipping_snapshot')->nullable()->after('shipping_rate_id');

            $table->foreign('shipping_method_id', 'order_ship_method_fk')
                ->references('id')
                ->on('shipping_methods')
                ->nullOnDelete();
            $table->foreign('shipping_zone_id', 'order_ship_zone_fk')
                ->references('id')
                ->on('shipping_zones')
                ->nullOnDelete();
            $table->foreign('shipping_rate_id', 'order_ship_rate_fk')
                ->references('id')
                ->on('shipping_rates')
                ->nullOnDelete();

            $table->index(['shipping_zone_id', 'delivery_status'], 'order_ship_zone_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('order_ship_method_fk');
            $table->dropForeign('order_ship_zone_fk');
            $table->dropForeign('order_ship_rate_fk');
            $table->dropIndex('order_ship_zone_status_idx');
            $table->dropColumn([
                'shipping_method_id',
                'shipping_zone_id',
                'shipping_rate_id',
                'shipping_snapshot',
            ]);
        });
    }
};
