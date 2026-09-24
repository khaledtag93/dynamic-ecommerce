<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'barcode')) {
                $table->string('barcode')->nullable()->after('sku');
                $table->unique('barcode', 'product_variants_barcode_unique');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('barcode', 'products_barcode_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_barcode_lookup_idx');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'barcode')) {
                $table->dropUnique('product_variants_barcode_unique');
                $table->dropColumn('barcode');
            }
        });
    }
};
