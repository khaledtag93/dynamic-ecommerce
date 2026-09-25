<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id');
            $table->string('country_code', 2);
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->timestamps();

            $table->foreign('shipping_zone_id', 'ship_city_zone_fk')
                ->references('id')
                ->on('shipping_zones')
                ->cascadeOnDelete();

            $table->unique(['country_code', 'normalized_name'], 'ship_city_country_name_uq');
            $table->index(['shipping_zone_id', 'name'], 'ship_city_zone_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_cities');
    }
};
