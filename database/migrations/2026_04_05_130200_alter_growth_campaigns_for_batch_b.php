<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('growth_campaigns', 'segment_id')) {
                $table->foreignId('segment_id')->nullable()->after('audience_type')->constrained('growth_audience_segments')->nullOnDelete();
            }

            if (! Schema::hasColumn('growth_campaigns', 'default_template_key')) {
                $table->string('default_template_key')->nullable()->after('subject');
            }

            if (! Schema::hasColumn('growth_campaigns', 'subject_translations')) {
                $table->json('subject_translations')->nullable()->after('message');
            }

            if (! Schema::hasColumn('growth_campaigns', 'message_translations')) {
                $table->json('message_translations')->nullable()->after('subject_translations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('growth_campaigns', 'segment_id')) {
                $table->dropConstrainedForeignId('segment_id');
            }
            if (Schema::hasColumn('growth_campaigns', 'default_template_key')) {
                $table->dropColumn('default_template_key');
            }
            if (Schema::hasColumn('growth_campaigns', 'subject_translations')) {
                $table->dropColumn('subject_translations');
            }
            if (Schema::hasColumn('growth_campaigns', 'message_translations')) {
                $table->dropColumn('message_translations');
            }
        });
    }
};
