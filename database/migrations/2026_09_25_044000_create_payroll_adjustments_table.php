<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id');
            $table->string('type', 30);
            $table->string('label', 160);
            $table->decimal('amount', 14, 2);
            $table->decimal('quantity', 12, 3)->nullable();
            $table->decimal('rate', 14, 4)->nullable();
            $table->text('reason');
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('payroll_entry_id', 'payroll_adj_entry_fk')
                ->references('id')
                ->on('payroll_entries')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id', 'payroll_adj_actor_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['payroll_entry_id', 'type'], 'payroll_adj_entry_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
