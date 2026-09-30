<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->json('allocation')->nullable()->after('amount');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('commercial_refund_total', 12, 2)->nullable()->after('refund_total');
        });

        DB::table('orders')->update([
            'commercial_refund_total' => DB::raw('refund_total'),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('commercial_refund_total');
        });

        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropColumn('allocation');
        });
    }
};
