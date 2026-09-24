<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->string('discount_type', 20)->nullable()->after('customer_name');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->string('discount_reason')->nullable()->after('discount_value');
        });

        Schema::table('pos_cart_items', function (Blueprint $table) {
            $table->string('discount_type', 20)->nullable()->after('quantity');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->string('discount_reason')->nullable()->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('pos_cart_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_reason']);
        });

        Schema::table('pos_carts', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_reason']);
        });
    }
};
