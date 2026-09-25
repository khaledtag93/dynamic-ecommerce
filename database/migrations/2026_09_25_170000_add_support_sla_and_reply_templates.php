<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_cases', function (Blueprint $table) {
            $table->timestamp('first_response_due_at')->nullable()->after('source');
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            $table->index(['status', 'first_response_due_at'], 'support_cases_first_response_due_idx');
            $table->index(['status', 'resolution_due_at'], 'support_cases_resolution_due_idx');
        });

        Schema::create('support_reply_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('name_ar', 160)->nullable();
            $table->text('body');
            $table->text('body_ar')->nullable();
            $table->string('visibility', 20)->default('customer');
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'support_reply_templates_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_reply_templates');

        Schema::table('support_cases', function (Blueprint $table) {
            $table->dropIndex('support_cases_first_response_due_idx');
            $table->dropIndex('support_cases_resolution_due_idx');
            $table->dropColumn(['first_response_due_at', 'resolution_due_at']);
        });
    }
};
