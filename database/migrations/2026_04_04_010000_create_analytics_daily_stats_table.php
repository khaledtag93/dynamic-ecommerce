<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_daily_stats')) {
            return;
        }

        Schema::create('analytics_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->unique();
            $table->unsignedInteger('product_views')->default(0);
            $table->unsignedInteger('cart_views')->default(0);
            $table->unsignedInteger('add_to_cart_count')->default(0);
            $table->unsignedInteger('remove_from_cart_count')->default(0);
            $table->unsignedInteger('checkout_starts')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('users_count')->default(0);
            $table->decimal('revenue_gross', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('average_order_value', 14, 2)->default(0);
            $table->decimal('cart_abandonment_rate', 8, 4)->default(0);
            $table->decimal('checkout_completion_rate', 8, 4)->default(0);
            $table->decimal('view_to_cart_rate', 8, 4)->default(0);
            $table->decimal('view_to_purchase_rate', 8, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('aggregated_at')->nullable()->index();
            $table->timestamps();

            $table->index(['stat_date', 'revenue_gross']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_stats');
    }
};
