<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id');
            $table->foreignId('employee_profile_id');

            $table->string('employee_code_snapshot', 40);
            $table->string('employee_name_snapshot', 255);
            $table->string('pay_basis_snapshot', 30);
            $table->decimal('base_rate_snapshot', 14, 2);
            $table->string('currency_snapshot', 3);

            $table->unsignedInteger('attendance_session_count')->default(0);
            $table->unsignedInteger('net_work_minutes')->default(0);
            $table->decimal('paid_leave_days', 7, 2)->default(0);
            $table->decimal('unpaid_leave_days', 7, 2)->default(0);

            $table->decimal('base_pay', 14, 2)->default(0);
            $table->decimal('overtime_pay', 14, 2)->default(0);
            $table->decimal('allowances_total', 14, 2)->default(0);
            $table->decimal('bonuses_total', 14, 2)->default(0);
            $table->decimal('deductions_total', 14, 2)->default(0);
            $table->decimal('gross_pay', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('payroll_run_id', 'payroll_entry_run_fk')
                ->references('id')
                ->on('payroll_runs')
                ->cascadeOnDelete();
            $table->foreign('employee_profile_id', 'payroll_entry_employee_fk')
                ->references('id')
                ->on('employee_profiles')
                ->restrictOnDelete();

            $table->unique(['payroll_run_id', 'employee_profile_id'], 'payroll_entry_run_employee_uq');
            $table->index(['employee_profile_id', 'created_at'], 'payroll_entry_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
