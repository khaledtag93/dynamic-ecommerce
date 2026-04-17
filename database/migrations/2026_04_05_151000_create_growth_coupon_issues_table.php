<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_coupon_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->foreignId('trigger_log_id')->nullable()->constrained('growth_trigger_logs')->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('growth_deliveries')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code')->nullable()->index();
            $table->string('offer_key')->nullable()->index();
            $table->string('offer_label')->nullable();
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'user_id'], 'growth_coupon_issues_campaign_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_coupon_issues');
    }
};
