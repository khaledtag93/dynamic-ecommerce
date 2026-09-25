<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id');
            $table->foreignId('shipping_zone_id');
            $table->decimal('amount', 12, 2);
            $table->decimal('free_shipping_threshold', 12, 2)->nullable();
            $table->string('threshold_basis', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('shipping_method_id', 'ship_rate_method_fk')
                ->references('id')
                ->on('shipping_methods')
                ->restrictOnDelete();
            $table->foreign('shipping_zone_id', 'ship_rate_zone_fk')
                ->references('id')
                ->on('shipping_zones')
                ->cascadeOnDelete();

            $table->unique(['shipping_method_id', 'shipping_zone_id'], 'ship_rate_method_zone_uq');
            $table->index(['shipping_zone_id', 'is_active'], 'ship_rate_zone_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
