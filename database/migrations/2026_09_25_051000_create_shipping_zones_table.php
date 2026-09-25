<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('name_ar', 120)->nullable();
            $table->string('country_code', 2);
            $table->string('country_name', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['country_code', 'is_active', 'priority'], 'ship_zone_country_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
