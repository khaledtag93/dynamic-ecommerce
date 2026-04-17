<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('event')->nullable();
            $table->string('trigger_status', 50);
            $table->string('source_channel', 50)->nullable();
            $table->string('action_type', 50);
            $table->string('target_channel', 50)->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(1);
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->string('admin_email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event', 'trigger_status']);
            $table->index(['source_channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_automation_rules');
    }
};
