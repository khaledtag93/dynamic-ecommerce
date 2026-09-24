<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id');
            $table->foreign('employee_profile_id', 'emp_leave_req_employee_fk')
                ->references('id')
                ->on('employee_profiles')
                ->restrictOnDelete();

            $table->foreignId('employee_leave_type_id');
            $table->foreign('employee_leave_type_id', 'emp_leave_req_type_fk')
                ->references('id')
                ->on('employee_leave_types')
                ->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('requested_days', 7, 2);
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id', 'emp_leave_req_reviewer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(
                ['employee_profile_id', 'status', 'starts_on'],
                'emp_leave_emp_status_idx'
            );
            $table->index(
                ['employee_leave_type_id', 'starts_on'],
                'emp_leave_type_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_requests');
    }
};
