<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendance_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_attendance_session_id')
                ->constrained('employee_attendance_sessions')
                ->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['employee_attendance_session_id', 'ends_at'],
                'emp_att_break_open_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_breaks');
    }
};
