<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable();

            $table->unsignedBigInteger('brand_id')->nullable()->after('category_id');
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();

            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'preorder', 'backorder'])->default('in_stock')->after('quantity');
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('quantity');

            $table->unsignedBigInteger('views_count')->default(0)->after('image');
            $table->decimal('rating_avg', 3, 2)->default(0)->after('views_count');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');

            $table->boolean('is_featured')->default(false)->after('status');

            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description')->nullable()->after('meta_title');

            $table->string('video_url')->nullable()->after('image');
        });
    }

    public function down(): void
{
    Schema::table('products', function (Blueprint $table) {

        if (Schema::hasColumn('products', 'barcode')) {
            $table->dropColumn('barcode');
        }
        if (Schema::hasColumn('products', 'brand_id')) {
            $table->dropColumn('brand_id');
        }
        if (Schema::hasColumn('products', 'stock_status')) {
            $table->dropColumn('stock_status');
        }
        if (Schema::hasColumn('products', 'low_stock_threshold')) {
            $table->dropColumn('low_stock_threshold');
        }
        if (Schema::hasColumn('products', 'views_count')) {
            $table->dropColumn('views_count');
        }
        if (Schema::hasColumn('products', 'rating_avg')) {
            $table->dropColumn('rating_avg');
        }
        if (Schema::hasColumn('products', 'rating_count')) {
            $table->dropColumn('rating_count');
        }
        if (Schema::hasColumn('products', 'is_featured')) {
            $table->dropColumn('is_featured');
        }
        if (Schema::hasColumn('products', 'meta_title')) {
            $table->dropColumn('meta_title');
        }
        if (Schema::hasColumn('products', 'meta_description')) {
            $table->dropColumn('meta_description');
        }
        if (Schema::hasColumn('products', 'video_url')) {
            $table->dropColumn('video_url');
        }
    });
}

};
