<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_attendance_session_id');
            $table->foreign('employee_attendance_session_id', 'emp_att_corr_session_fk')
                ->references('id')
                ->on('employee_attendance_sessions')
                ->restrictOnDelete();

            $table->foreignId('employee_profile_id');
            $table->foreign('employee_profile_id', 'emp_att_corr_employee_fk')
                ->references('id')
                ->on('employee_profiles')
                ->restrictOnDelete();

            $table->timestamp('previous_clock_in_at');
            $table->timestamp('previous_clock_out_at')->nullable();
            $table->timestamp('requested_clock_in_at');
            $table->timestamp('requested_clock_out_at')->nullable();

            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id', 'emp_att_corr_reviewer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['employee_attendance_session_id', 'status'],
                'emp_att_corr_session_idx'
            );
            $table->index(
                ['employee_profile_id', 'created_at'],
                'emp_att_corr_employee_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_corrections');
    }
};
