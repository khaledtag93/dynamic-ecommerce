<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore the table only if an existing environment lost its schema
     * while the original 2026-09-25 migration is already marked as run.
     * Normal installations keep their original table and data untouched.
     */
    public function up(): void
    {
        if (Schema::hasTable('employee_attendance_breaks')) {
            return;
        }

        Schema::create('employee_attendance_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_attendance_session_id');
            $table->foreign('employee_attendance_session_id', 'emp_att_break_session_fk')
                ->references('id')
                ->on('employee_attendance_sessions')
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

    /**
     * Do not drop a historical attendance table on rollback: it might have
     * existed before this recovery migration and may now contain real data.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
