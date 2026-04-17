<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->nullable()->after('sale_price');
            }
            if (! Schema::hasColumn('products', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('products', 'reorder_point')) {
                $table->unsignedInteger('reorder_point')->default(0)->after('low_stock_threshold');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->nullable()->after('sale_price');
            }
            if (! Schema::hasColumn('product_variants', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('stock');
            }
            if (! Schema::hasColumn('product_variants', 'reorder_point')) {
                $table->unsignedInteger('reorder_point')->default(0)->after('expiration_date');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'cost_total')) {
                $table->decimal('cost_total', 12, 2)->default(0)->after('refund_total');
            }
            if (! Schema::hasColumn('orders', 'profit_total')) {
                $table->decimal('profit_total', 12, 2)->default(0)->after('cost_total');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->default(0)->after('unit_price');
            }
            if (! Schema::hasColumn('order_items', 'profit_amount')) {
                $table->decimal('profit_amount', 12, 2)->default(0)->after('line_total');
            }
            if (! Schema::hasColumn('order_items', 'expires_at')) {
                $table->date('expires_at')->nullable()->after('profit_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['unit_cost', 'profit_amount', 'expires_at'] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['cost_total', 'profit_total'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            foreach (['cost_price', 'expiration_date', 'reorder_point'] as $column) {
                if (Schema::hasColumn('product_variants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            foreach (['cost_price', 'expiration_date', 'reorder_point'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
