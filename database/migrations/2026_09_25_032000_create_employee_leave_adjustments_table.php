<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id');
            $table->foreign('employee_profile_id', 'emp_leave_adj_employee_fk')
                ->references('id')
                ->on('employee_profiles')
                ->restrictOnDelete();

            $table->foreignId('employee_leave_type_id');
            $table->foreign('employee_leave_type_id', 'emp_leave_adj_type_fk')
                ->references('id')
                ->on('employee_leave_types')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('type', 30)->default('adjustment');
            $table->decimal('days', 7, 2);
            $table->text('reason');
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id', 'emp_leave_adj_actor_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['employee_profile_id', 'employee_leave_type_id', 'year'],
                'emp_leave_adj_balance_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_adjustments');
    }
};
