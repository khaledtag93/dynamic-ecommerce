<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->string('receipt_method')->default('legacy_unknown')->after('request_hash');
            $table->timestamp('reversed_at')->nullable()->after('received_at');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn([
                'receipt_method',
                'reversed_at',
                'reversed_by',
                'reversal_reason',
            ]);
        });
    }
};
