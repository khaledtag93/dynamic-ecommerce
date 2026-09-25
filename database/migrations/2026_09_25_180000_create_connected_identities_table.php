<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_user_id', 191);
            $table->string('provider_email')->nullable();
            $table->timestamp('provider_email_verified_at')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id'], 'connected_identity_provider_uid_unique');
            $table->unique(['user_id', 'provider'], 'connected_identity_user_provider_unique');
            $table->index(['provider', 'provider_email'], 'connected_identity_provider_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_identities');
    }
};
