<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->foreignId('order_id');
            $table->foreignId('user_id')->nullable();
            $table->string('status', 30)->default('requested');
            $table->text('customer_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('exchange_order_id')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable();
            $table->foreignId('received_by_user_id')->nullable();
            $table->foreignId('completed_by_user_id')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id', 'rma_order_fk')
                ->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('user_id', 'rma_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('exchange_order_id', 'rma_exchange_order_fk')
                ->references('id')->on('orders')->nullOnDelete();
            $table->foreign('reviewed_by_user_id', 'rma_reviewed_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('received_by_user_id', 'rma_received_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by_user_id', 'rma_completed_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'created_at'], 'rma_status_created_idx');
            $table->index(['order_id', 'status'], 'rma_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
