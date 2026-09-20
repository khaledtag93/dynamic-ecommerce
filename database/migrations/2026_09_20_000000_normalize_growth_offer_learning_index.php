<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('growth_offer_learning_snapshots')) {
            return;
        }

        Schema::table('growth_offer_learning_snapshots', function (Blueprint $table) {
            $table->string('campaign_key', 120)->nullable()->change();
            $table->string('retention_stage', 64)->nullable()->change();
            $table->string('offer_bias', 64)->nullable()->change();
            $table->string('offer_key', 120)->nullable()->change();
            $table->string('experiment_variant', 120)->nullable()->change();
        });

        if (! $this->indexExists('growth_offer_learning_snapshots', 'growth_offer_learning_unique')) {
            Schema::table('growth_offer_learning_snapshots', function (Blueprint $table) {
                $table->unique(
                    ['campaign_key', 'retention_stage', 'offer_bias', 'experiment_variant'],
                    'growth_offer_learning_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Intentionally keep the safer key lengths. Re-expanding these columns
        // would reintroduce the MySQL index-width problem this migration fixes.
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return count(DB::select(
                'SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?',
                [$index]
            )) > 0;
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('".$table."')"))
                ->contains(fn ($row) => (string) ($row->name ?? '') === $index);
        }

        return false;
    }
};
