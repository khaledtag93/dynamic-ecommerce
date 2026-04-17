<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('growth_customer_scores')) {
            Schema::table('growth_customer_scores', function (Blueprint $table) {
                if (! Schema::hasColumn('growth_customer_scores', 'adaptive_offer_preference')) {
                    $table->string('adaptive_offer_preference')->nullable()->after('offer_bias');
                }
                if (! Schema::hasColumn('growth_customer_scores', 'adaptive_confidence')) {
                    $table->decimal('adaptive_confidence', 8, 2)->default(0)->after('adaptive_offer_preference');
                }
                if (! Schema::hasColumn('growth_customer_scores', 'winback_priority_score')) {
                    $table->decimal('winback_priority_score', 8, 2)->default(0)->after('adaptive_confidence');
                }
                if (! Schema::hasColumn('growth_customer_scores', 'winback_priority_band')) {
                    $table->string('winback_priority_band')->nullable()->after('winback_priority_score');
                }
                if (! Schema::hasColumn('growth_customer_scores', 'winback_ready_at')) {
                    $table->timestamp('winback_ready_at')->nullable()->after('winback_priority_band');
                }
                if (! Schema::hasColumn('growth_customer_scores', 'recommended_discount_type')) {
                    $table->string('recommended_discount_type')->nullable()->after('winback_ready_at');
                }
                if (! Schema::hasColumn('growth_customer_scores', 'recommended_discount_value')) {
                    $table->decimal('recommended_discount_value', 8, 2)->nullable()->after('recommended_discount_type');
                }
            });
        }

        if (! Schema::hasTable('growth_offer_learning_snapshots')) {
            Schema::create('growth_offer_learning_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
                $table->foreignId('experiment_id')->nullable()->constrained('growth_experiments')->nullOnDelete();
                $table->string('campaign_key')->nullable()->index();
                $table->string('retention_stage')->nullable()->index();
                $table->string('offer_bias')->nullable()->index();
                $table->string('offer_key')->nullable()->index();
                $table->string('experiment_variant')->nullable()->index();
                $table->unsignedInteger('deliveries')->default(0);
                $table->unsignedInteger('converted')->default(0);
                $table->decimal('conversion_rate', 8, 2)->default(0);
                $table->decimal('revenue', 14, 2)->default(0);
                $table->decimal('learning_score', 10, 2)->default(0);
                $table->boolean('is_recommended')->default(false);
                $table->timestamp('calculated_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['campaign_key', 'retention_stage', 'offer_bias', 'experiment_variant'], 'growth_offer_learning_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('growth_offer_learning_snapshots')) {
            Schema::dropIfExists('growth_offer_learning_snapshots');
        }

        if (Schema::hasTable('growth_customer_scores')) {
            Schema::table('growth_customer_scores', function (Blueprint $table) {
                $drops = [];
                foreach (['adaptive_offer_preference','adaptive_confidence','winback_priority_score','winback_priority_band','winback_ready_at','recommended_discount_type','recommended_discount_value'] as $col) {
                    if (Schema::hasColumn('growth_customer_scores', $col)) {
                        $drops[] = $col;
                    }
                }
                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }
    }
};
