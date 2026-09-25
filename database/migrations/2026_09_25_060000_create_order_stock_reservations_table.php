<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('order_item_id');
            $table->foreignId('product_id')->nullable();
            $table->foreignId('product_variant_id')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('status', 30)->default('reserved');
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 120)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('order_id', 'stock_res_order_fk')
                ->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id', 'stock_res_item_fk')
                ->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('product_id', 'stock_res_product_fk')
                ->references('id')->on('products')->nullOnDelete();
            $table->foreign('product_variant_id', 'stock_res_variant_fk')
                ->references('id')->on('product_variants')->nullOnDelete();

            $table->unique('order_item_id', 'stock_res_order_item_uq');
            $table->index(['status', 'expires_at'], 'stock_res_expiry_idx');
            $table->index(['order_id', 'status'], 'stock_res_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stock_reservations');
    }
};
