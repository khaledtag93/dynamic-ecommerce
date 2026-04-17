<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('campaign_key')->unique();
            $table->string('campaign_type')->index();
            $table->string('trigger_event')->nullable()->index();
            $table->string('channel')->default('in_app')->index();
            $table->string('audience_type')->default('user_or_session');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('coupon_code')->nullable()->index();
            $table->json('config')->nullable();
            $table->json('stats')->nullable();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_messaging_enabled')->default(false)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_campaigns');
    }
};
