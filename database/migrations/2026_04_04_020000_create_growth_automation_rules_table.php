<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rule_key')->unique();
            $table->string('trigger_type')->index();
            $table->string('channel')->default('onsite');
            $table->string('audience_type')->default('session');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('coupon_code')->nullable()->index();
            $table->json('config')->nullable();
            $table->json('stats')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_automation_rules');
    }
};
