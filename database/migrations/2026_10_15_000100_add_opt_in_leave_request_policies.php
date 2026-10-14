<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leave_types', function (Blueprint $table) {
            // Explicit opt-in; all existing leave types retain their original behavior.
            $table->string('duration_mode', 16)->default('legacy');
            $table->unsignedSmallInteger('fixed_duration_days')->nullable();
            $table->unsignedSmallInteger('min_request_days')->nullable();
            $table->unsignedSmallInteger('max_request_days')->nullable();
            $table->string('counting_mode', 16)->default('calendar');
            $table->json('working_weekdays')->nullable();
            $table->json('holiday_dates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_types', function (Blueprint $table) {
            $table->dropColumn([
                'duration_mode',
                'fixed_duration_days',
                'min_request_days',
                'max_request_days',
                'counting_mode',
                'working_weekdays',
                'holiday_dates',
            ]);
        });
    }
};
