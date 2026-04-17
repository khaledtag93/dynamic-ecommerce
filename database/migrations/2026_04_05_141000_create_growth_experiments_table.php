<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->string('name');
            $table->string('experiment_key')->unique();
            $table->text('description')->nullable();
            $table->json('variants');
            $table->json('stats')->nullable();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['campaign_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_experiments');
    }
};
