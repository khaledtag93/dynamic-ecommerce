<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('billing_country');
            $table->json('coupon_snapshot')->nullable()->after('coupon_code');
            $table->timestamp('cancelled_at')->nullable()->after('coupon_snapshot');
            $table->string('cancelled_reason')->nullable()->after('cancelled_at');
            $table->decimal('refund_total', 12, 2)->default(0)->after('cancelled_reason');
            $table->timestamp('refunded_at')->nullable()->after('refund_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'coupon_code',
                'coupon_snapshot',
                'cancelled_at',
                'cancelled_reason',
                'refund_total',
                'refunded_at',
            ]);
        });
    }
};
