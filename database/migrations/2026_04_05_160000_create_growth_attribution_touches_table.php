<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_attribution_touches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->nullable()->constrained('growth_deliveries')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->foreignId('experiment_id')->nullable()->constrained('growth_experiments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('touch_type')->default('assist');
            $table->string('status')->default('attributed');
            $table->decimal('attribution_weight', 8, 4)->default(1);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('profit_total', 14, 2)->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('attributed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'order_id'], 'growth_attr_delivery_order_unique');
            $table->index(['campaign_id', 'occurred_at'], 'growth_attr_campaign_occurred_idx');
            $table->index(['user_id', 'occurred_at'], 'growth_attr_user_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_attribution_touches');
    }
};
