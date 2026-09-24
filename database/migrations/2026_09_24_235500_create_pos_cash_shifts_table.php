<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('closing_cash_counted', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('cash_variance', 12, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['cashier_user_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_shifts');
    }
};
