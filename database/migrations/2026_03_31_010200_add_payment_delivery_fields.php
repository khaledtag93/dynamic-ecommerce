<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('transaction_reference');
            $table->string('provider_status')->nullable()->after('provider');
            $table->timestamp('authorized_at')->nullable()->after('paid_at');
            $table->timestamp('failed_at')->nullable()->after('authorized_at');
            $table->timestamp('refunded_at')->nullable()->after('failed_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_status')->default('pending')->after('payment_method');
            $table->string('delivery_method')->default('standard_shipping')->after('delivery_status');
            $table->string('shipping_provider')->nullable()->after('delivery_method');
            $table->string('tracking_number')->nullable()->after('shipping_provider');
            $table->date('estimated_delivery_date')->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('estimated_delivery_date');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->text('delivery_notes')->nullable()->after('delivered_at');
            $table->index(['delivery_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_status', 'created_at']);
            $table->dropColumn([
                'delivery_status',
                'delivery_method',
                'shipping_provider',
                'tracking_number',
                'estimated_delivery_date',
                'shipped_at',
                'delivered_at',
                'delivery_notes',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['notes', 'provider_status', 'authorized_at', 'failed_at', 'refunded_at']);
        });
    }
};
