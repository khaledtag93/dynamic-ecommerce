<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'sales_channel')) {
                $table->string('sales_channel', 30)->default('storefront')->after('user_id')->index();
            }

            $table->string('customer_email')->nullable()->change();
            $table->string('customer_phone')->nullable()->change();
            $table->string('shipping_address_line_1')->nullable()->change();
            $table->string('shipping_city')->nullable()->change();
            $table->string('shipping_country')->nullable()->change();
        });

        Schema::create('pos_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 30)->default('open')->index();
            $table->string('open_token')->nullable()->unique();
            $table->string('customer_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cashier_user_id', 'status']);
        });

        Schema::create('pos_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_cart_id')->constrained('pos_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('item_key', 80);
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['pos_cart_id', 'item_key']);
            $table->index(['product_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cart_items');
        Schema::dropIfExists('pos_carts');

        if (Schema::hasColumn('orders', 'sales_channel')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('sales_channel');
            });
        }

        DB::table('orders')->whereNull('customer_email')->update(['customer_email' => '']);
        DB::table('orders')->whereNull('customer_phone')->update(['customer_phone' => '']);
        DB::table('orders')->whereNull('shipping_address_line_1')->update(['shipping_address_line_1' => '']);
        DB::table('orders')->whereNull('shipping_city')->update(['shipping_city' => '']);
        DB::table('orders')->whereNull('shipping_country')->update(['shipping_country' => 'Egypt']);

        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable(false)->change();
            $table->string('customer_phone')->nullable(false)->change();
            $table->string('shipping_address_line_1')->nullable(false)->change();
            $table->string('shipping_city')->nullable(false)->change();
            $table->string('shipping_country')->nullable(false)->change();
        });
    }
};
