<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('growth_deliveries', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->after('last_attempt_at');
                $table->index(['status', 'scheduled_for'], 'growth_deliveries_status_scheduled_for_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('growth_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('growth_deliveries', 'scheduled_for')) {
                $table->dropIndex('growth_deliveries_status_scheduled_for_index');
                $table->dropColumn('scheduled_for');
            }
        });
    }
};
