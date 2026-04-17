<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('growth_customer_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('completed_orders_count')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('average_order_value', 12, 2)->default(0);
            $table->unsignedInteger('days_since_last_order')->default(9999);
            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('view_count_30d')->default(0);
            $table->unsignedInteger('cart_count_30d')->default(0);
            $table->unsignedInteger('checkout_count_30d')->default(0);
            $table->unsignedInteger('purchase_count_90d')->default(0);
            $table->decimal('ltv_score', 8, 2)->default(0);
            $table->decimal('churn_risk_score', 8, 2)->default(0);
            $table->decimal('engagement_score', 8, 2)->default(0);
            $table->string('retention_stage', 50)->default('new');
            $table->string('next_best_campaign', 120)->nullable();
            $table->string('offer_bias', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['retention_stage', 'ltv_score']);
            $table->index(['next_best_campaign', 'churn_risk_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_customer_scores');
    }
};
