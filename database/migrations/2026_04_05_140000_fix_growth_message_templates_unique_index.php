<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('growth_message_templates')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM growth_message_templates');

            $hasSingle = collect($indexes)->contains(fn ($index) => $index->Key_name === 'growth_message_templates_template_key_unique');
            $hasComposite = collect($indexes)
                ->where('Key_name', 'growth_message_templates_template_key_locale_unique')
                ->pluck('Column_name')
                ->values()
                ->all();

            if ($hasSingle) {
                Schema::table('growth_message_templates', function (Blueprint $table) {
                    $table->dropUnique('growth_message_templates_template_key_unique');
                });
            }

            if ($hasComposite !== ['template_key', 'locale']) {
                Schema::table('growth_message_templates', function (Blueprint $table) {
                    $table->unique(['template_key', 'locale'], 'growth_message_templates_template_key_locale_unique');
                });
            }

            return;
        }

        if ($driver === 'sqlite') {
            $indexList = DB::select("PRAGMA index_list('growth_message_templates')");
            $indexNames = collect($indexList)->pluck('name')->all();

            if (in_array('growth_message_templates_template_key_unique', $indexNames, true)) {
                Schema::table('growth_message_templates', function (Blueprint $table) {
                    $table->dropUnique('growth_message_templates_template_key_unique');
                });
            }

            if (! in_array('growth_message_templates_template_key_locale_unique', $indexNames, true)) {
                Schema::table('growth_message_templates', function (Blueprint $table) {
                    $table->unique(['template_key', 'locale'], 'growth_message_templates_template_key_locale_unique');
                });
            }

            return;
        }

        Schema::table('growth_message_templates', function (Blueprint $table) {
            try {
                $table->dropUnique('growth_message_templates_template_key_unique');
            } catch (\Throwable $exception) {
            }

            $table->unique(['template_key', 'locale'], 'growth_message_templates_template_key_locale_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('growth_message_templates')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM growth_message_templates');
            $hasComposite = collect($indexes)->contains(fn ($index) => $index->Key_name === 'growth_message_templates_template_key_locale_unique');

            if ($hasComposite) {
                Schema::table('growth_message_templates', function (Blueprint $table) {
                    $table->dropUnique('growth_message_templates_template_key_locale_unique');
                });
            }

            $hasSingle = collect(DB::select('SHOW INDEX FROM growth_message_templates'))
                ->contains(fn ($index) => $index->Key_name === 'growth_message_templates_template_key_unique');

            if (! $hasSingle) {
                Schema::table('growth_message_templates', function (Blueprint $table) {
                    $table->unique('template_key', 'growth_message_templates_template_key_unique');
                });
            }

            return;
        }

        Schema::table('growth_message_templates', function (Blueprint $table) {
            try {
                $table->dropUnique('growth_message_templates_template_key_locale_unique');
            } catch (\Throwable $exception) {
            }

            $table->unique('template_key', 'growth_message_templates_template_key_unique');
        });
    }
};
