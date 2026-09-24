<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('pay_date')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['starts_on', 'ends_on'], 'payroll_period_range_uq');
            $table->index(['status', 'starts_on'], 'payroll_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
