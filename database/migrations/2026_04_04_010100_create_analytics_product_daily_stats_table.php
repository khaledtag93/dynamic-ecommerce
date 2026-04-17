<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_product_daily_stats')) {
            return;
        }

        Schema::create('analytics_product_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('product_slug')->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('add_to_cart_count')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->unsignedInteger('purchased_quantity')->default(0);
            $table->decimal('revenue_gross', 14, 2)->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('aggregated_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['stat_date', 'product_id']);
            $table->index(['stat_date', 'views']);
            $table->index(['stat_date', 'revenue_gross']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_product_daily_stats');
    }
};
