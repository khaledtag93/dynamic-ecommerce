<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->foreignId('trigger_log_id')->nullable()->constrained('growth_trigger_logs')->nullOnDelete();
            $table->foreignId('message_log_id')->nullable()->constrained('growth_message_logs')->nullOnDelete();
            $table->foreignId('experiment_id')->nullable()->constrained('growth_experiments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel')->default('in_app')->index();
            $table->string('provider')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('experiment_variant')->nullable()->index();
            $table->json('payload')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_deliveries');
    }
};
