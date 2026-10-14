<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->foreignId('manager_employee_profile_id')->nullable();
            $table->foreign('manager_employee_profile_id', 'emp_profile_manager_fk')
                ->references('id')->on('employee_profiles')->restrictOnDelete();
            $table->index('manager_employee_profile_id', 'emp_profile_manager_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropForeign('emp_profile_manager_fk');
            $table->dropIndex('emp_profile_manager_idx');
            $table->dropColumn('manager_employee_profile_id');
        });
    }
};
