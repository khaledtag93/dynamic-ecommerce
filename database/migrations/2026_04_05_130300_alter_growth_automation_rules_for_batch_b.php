<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_automation_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('growth_automation_rules', 'segment_id')) {
                $table->foreignId('segment_id')->nullable()->after('audience_type')->constrained('growth_audience_segments')->nullOnDelete();
            }

            if (! Schema::hasColumn('growth_automation_rules', 'default_template_key')) {
                $table->string('default_template_key')->nullable()->after('subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('growth_automation_rules', function (Blueprint $table) {
            if (Schema::hasColumn('growth_automation_rules', 'segment_id')) {
                $table->dropConstrainedForeignId('segment_id');
            }
            if (Schema::hasColumn('growth_automation_rules', 'default_template_key')) {
                $table->dropColumn('default_template_key');
            }
        });
    }
};
