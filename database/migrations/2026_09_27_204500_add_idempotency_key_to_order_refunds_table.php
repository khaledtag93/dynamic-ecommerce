<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('return_request_id');
            $table->unique(['order_id', 'idempotency_key'], 'refund_order_idempotency_uq');
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropUnique('refund_order_idempotency_uq');
            $table->dropColumn('idempotency_key');
        });
    }
};
