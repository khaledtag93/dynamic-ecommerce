<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_attendance_sessions', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->index();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['voided_by_user_id']);
            $table->dropIndex(['voided_at']);
            $table->dropColumn(['voided_at', 'voided_by_user_id', 'void_reason']);
        });
    }
};
