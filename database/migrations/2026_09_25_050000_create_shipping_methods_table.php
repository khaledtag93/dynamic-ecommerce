<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('name_ar', 120)->nullable();
            $table->string('type', 30)->default('shipping');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->unsignedSmallInteger('eta_min_days')->nullable();
            $table->unsignedSmallInteger('eta_max_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'ship_method_active_idx');
        });

        DB::table('shipping_methods')->insert([
            [
                'code' => 'standard_shipping',
                'name' => 'Standard shipping',
                'name_ar' => 'الشحن القياسي',
                'type' => 'shipping',
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'express_shipping',
                'name' => 'Express shipping',
                'name_ar' => 'الشحن السريع',
                'type' => 'shipping',
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'store_pickup',
                'name' => 'Store pickup',
                'name_ar' => 'الاستلام من المتجر',
                'type' => 'pickup',
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
