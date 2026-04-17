<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('template_key');
            $table->string('channel')->default('in_app')->index();
            $table->string('locale')->default('ar')->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('tokens')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->timestamps();

            $table->unique(['template_key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_message_templates');
    }
};
