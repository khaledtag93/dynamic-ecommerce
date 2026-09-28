<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('inventory_cost_price', 12, 2)->nullable()->after('cost_price');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('inventory_cost_price', 12, 2)->nullable()->after('cost_price');
        });

        DB::table('products')
            ->whereNotNull('cost_price')
            ->update(['inventory_cost_price' => DB::raw('cost_price')]);

        DB::table('product_variants')
            ->whereNotNull('cost_price')
            ->update(['inventory_cost_price' => DB::raw('cost_price')]);
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('inventory_cost_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('inventory_cost_price');
        });
    }
};
