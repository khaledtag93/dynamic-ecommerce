<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('name_ar', 120)->nullable();
            $table->boolean('is_paid')->default(true);
            $table->decimal('default_annual_entitlement_days', 7, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name'], 'emp_leave_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_types');
    }
};
