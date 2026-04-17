<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_cohort_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('cohort_key')->unique();
            $table->string('cohort_label');
            $table->date('cohort_start_date');
            $table->date('cohort_end_date');
            $table->unsignedInteger('cohort_size')->default(0);
            $table->unsignedInteger('retained_30d')->default(0);
            $table->unsignedInteger('retained_60d')->default(0);
            $table->unsignedInteger('retained_90d')->default(0);
            $table->decimal('retention_rate_30d', 8, 2)->default(0);
            $table->decimal('retention_rate_60d', 8, 2)->default(0);
            $table->decimal('retention_rate_90d', 8, 2)->default(0);
            $table->decimal('revenue_30d', 14, 2)->default(0);
            $table->decimal('revenue_60d', 14, 2)->default(0);
            $table->decimal('revenue_90d', 14, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index('cohort_start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_cohort_snapshots');
    }
};
