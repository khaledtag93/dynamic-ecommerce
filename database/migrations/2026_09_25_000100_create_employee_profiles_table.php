<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('employee_code', 40)->unique();
            $table->string('job_title', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('employment_type', 30)->default('full_time');
            $table->string('status', 30)->default('active');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'employment_type']);
            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
