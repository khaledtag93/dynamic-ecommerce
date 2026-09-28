<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->dropUnique(['employee_profile_id']);
            $table->unique(
                ['employee_profile_id', 'effective_from'],
                'emp_comp_employee_effective_uq'
            );
            $table->index(
                ['employee_profile_id', 'effective_from', 'effective_to'],
                'emp_comp_employee_period_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->dropIndex('emp_comp_employee_period_idx');
            $table->dropUnique('emp_comp_employee_effective_uq');
            $table->unique('employee_profile_id');
        });
    }
};
