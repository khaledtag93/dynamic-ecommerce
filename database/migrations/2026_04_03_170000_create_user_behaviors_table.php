<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_behaviors')) {
            return;
        }

        Schema::create('user_behaviors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('event', 60)->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['event', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_behaviors');
    }
};
