<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_product_daily_stats', function (Blueprint $table) {
            $table->decimal('realized_cogs', 14, 2)->nullable()->after('revenue_gross');
            $table->decimal('profit_total', 14, 2)->nullable()->after('realized_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_product_daily_stats', function (Blueprint $table) {
            $table->dropColumn(['realized_cogs', 'profit_total']);
        });
    }
};
