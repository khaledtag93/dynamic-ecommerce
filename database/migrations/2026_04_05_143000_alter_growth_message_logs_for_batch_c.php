<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('growth_message_logs')) {
            return;
        }

        $hasDeliveryId = Schema::hasColumn('growth_message_logs', 'delivery_id');
        $hasExperimentId = Schema::hasColumn('growth_message_logs', 'experiment_id');
        $hasVariant = Schema::hasColumn('growth_message_logs', 'experiment_variant');

        Schema::table('growth_message_logs', function (Blueprint $table) use ($hasDeliveryId, $hasExperimentId, $hasVariant) {
            if (! $hasDeliveryId) {
                $table->foreignId('delivery_id')->nullable()->after('trigger_log_id')->constrained('growth_deliveries')->nullOnDelete();
            }

            if (! $hasExperimentId) {
                $table->foreignId('experiment_id')->nullable()->after('delivery_id')->constrained('growth_experiments')->nullOnDelete();
            }

            if (! $hasVariant) {
                $table->string('experiment_variant')->nullable()->after('coupon_code')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('growth_message_logs')) {
            return;
        }

        $hasDeliveryId = Schema::hasColumn('growth_message_logs', 'delivery_id');
        $hasExperimentId = Schema::hasColumn('growth_message_logs', 'experiment_id');
        $hasVariant = Schema::hasColumn('growth_message_logs', 'experiment_variant');

        Schema::table('growth_message_logs', function (Blueprint $table) use ($hasDeliveryId, $hasExperimentId, $hasVariant) {
            if ($hasVariant) {
                $table->dropIndex(['experiment_variant']);
                $table->dropColumn('experiment_variant');
            }

            if ($hasExperimentId) {
                $table->dropConstrainedForeignId('experiment_id');
            }

            if ($hasDeliveryId) {
                $table->dropConstrainedForeignId('delivery_id');
            }
        });
    }
};
