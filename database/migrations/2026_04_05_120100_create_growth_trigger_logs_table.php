<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_trigger_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('growth_automation_rules')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('trigger_event')->nullable()->index();
            $table->string('status')->default('triggered')->index();
            $table->string('channel')->nullable();
            $table->json('audience_snapshot')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('triggered_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'user_id']);
            $table->index(['campaign_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_trigger_logs');
    }
};
