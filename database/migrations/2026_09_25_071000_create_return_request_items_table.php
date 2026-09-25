<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id');
            $table->foreignId('order_item_id');
            $table->unsignedInteger('requested_quantity');
            $table->unsignedInteger('approved_quantity')->nullable();
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('restock_quantity')->default(0);
            $table->string('reason_code', 50);
            $table->text('reason_details')->nullable();
            $table->string('requested_resolution', 30)->default('refund');
            $table->timestamps();

            $table->foreign('return_request_id', 'rma_item_request_fk')
                ->references('id')->on('return_requests')->cascadeOnDelete();
            $table->foreign('order_item_id', 'rma_item_order_item_fk')
                ->references('id')->on('order_items')->restrictOnDelete();

            $table->unique(['return_request_id', 'order_item_id'], 'rma_request_item_uq');
            $table->index(['order_item_id', 'requested_resolution'], 'rma_order_item_resolution_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
    }
};
