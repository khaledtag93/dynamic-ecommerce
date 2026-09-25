<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 180);
            $table->string('category', 80)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->string('source', 40)->default('customer_portal');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_staff_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('support_case_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 20);
            $table->string('visibility', 20)->default('customer');
            $table->text('body');
            $table->timestamps();

            $table->index(['support_case_id', 'created_at']);
            $table->index(['visibility', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_case_messages');
        Schema::dropIfExists('support_cases');
    }
};
