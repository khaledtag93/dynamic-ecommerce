<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->foreignId('customer_user_id')
                ->nullable()
                ->after('cashier_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_user_id');
        });
    }
};
