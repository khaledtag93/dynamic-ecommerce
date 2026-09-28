<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->unsignedInteger('received_quantity')->default(0)->after('quantity');
        });

        DB::table('purchase_items')
            ->whereIn('purchase_id', DB::table('purchases')->where('status', 'received')->select('id'))
            ->update(['received_quantity' => DB::raw('quantity')]);

        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['purchase_id', 'received_at']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();

            $table->unique(['purchase_receipt_id', 'purchase_item_id'], 'purchase_receipt_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });
    }
};
