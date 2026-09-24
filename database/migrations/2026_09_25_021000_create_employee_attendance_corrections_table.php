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
            $table->foreignId('employee_attendance_session_id')
                ->constrained('employee_attendance_sessions')
                ->restrictOnDelete();
            $table->foreignId('employee_profile_id')
                ->constrained('employee_profiles')
                ->restrictOnDelete();

            $table->timestamp('previous_clock_in_at');
            $table->timestamp('previous_clock_out_at')->nullable();
            $table->timestamp('requested_clock_in_at');
            $table->timestamp('requested_clock_out_at')->nullable();

            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
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
