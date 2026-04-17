<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_audience_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('segment_key')->unique();
            $table->string('audience_type')->default('user_or_session')->index();
            $table->text('description')->nullable();
            $table->json('filters')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_audience_segments');
    }
};
