<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->restrictOnDelete();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->string('source', 40)->default('admin');
            $table->text('clock_in_notes')->nullable();
            $table->text('clock_out_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'clock_out_at'], 'emp_attendance_open_idx');
            $table->index('clock_in_at', 'emp_attendance_clock_in_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_sessions');
    }
};
