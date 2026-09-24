<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receiving_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->unique()->constrained('purchase_items')->cascadeOnDelete();
            $table->unsignedInteger('verified_quantity')->default(0);
            $table->foreignId('last_scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'verified_quantity'], 'purchase_receiving_progress_purchase_qty_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receiving_progress');
    }
};
