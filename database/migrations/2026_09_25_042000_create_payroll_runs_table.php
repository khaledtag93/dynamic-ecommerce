<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->unique();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('approved_by_user_id')->nullable();
            $table->foreignId('paid_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('payroll_period_id', 'payroll_run_period_fk')
                ->references('id')
                ->on('payroll_periods')
                ->restrictOnDelete();
            $table->foreign('created_by_user_id', 'payroll_run_creator_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('approved_by_user_id', 'payroll_run_approver_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('paid_by_user_id', 'payroll_run_payer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['status', 'created_at'], 'payroll_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
