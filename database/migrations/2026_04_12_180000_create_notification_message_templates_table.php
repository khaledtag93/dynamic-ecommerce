<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event')->index();
            $table->string('channel')->index();
            $table->string('locale', 10)->default('ar')->index();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->json('tokens')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(100)->index();
            $table->timestamps();

            $table->unique(['event', 'channel', 'locale'], 'notification_message_templates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_message_templates');
    }
};
