<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->unique();
            $table->string('pay_basis', 30);
            $table->decimal('base_rate', 14, 2);
            $table->string('currency', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('overtime_eligible')->default(false);
            $table->decimal('overtime_rate_multiplier', 6, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_profile_id', 'emp_comp_employee_fk')
                ->references('id')
                ->on('employee_profiles')
                ->restrictOnDelete();

            $table->index(['effective_from', 'effective_to'], 'emp_comp_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_compensations');
    }
};
