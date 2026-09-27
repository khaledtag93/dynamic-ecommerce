<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'placed_at', 'id'], 'orders_statement_placed_idx');
            $table->index(['user_id', 'created_at', 'id'], 'orders_statement_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['order_id', 'paid_at', 'id'], 'payments_statement_paid_idx');
        });

        Schema::table('order_refunds', function (Blueprint $table) {
            $table->index(['order_id', 'processed_at', 'id'], 'refunds_statement_processed_idx');
            $table->index(['order_id', 'created_at', 'id'], 'refunds_statement_created_idx');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->index(['user_id', 'requested_at', 'id'], 'returns_statement_requested_idx');
            $table->index(['user_id', 'created_at', 'id'], 'returns_statement_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex('returns_statement_requested_idx');
            $table->dropIndex('returns_statement_created_idx');
        });

        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropIndex('refunds_statement_processed_idx');
            $table->dropIndex('refunds_statement_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_statement_paid_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_statement_placed_idx');
            $table->dropIndex('orders_statement_created_idx');
        });
    }
};
