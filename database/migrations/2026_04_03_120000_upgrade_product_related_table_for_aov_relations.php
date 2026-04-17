<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_related', function (Blueprint $table) {
            if (! Schema::hasColumn('product_related', 'relation_type')) {
                $table->string('relation_type', 20)->default('related')->after('related_product_id');
            }

            if (! Schema::hasColumn('product_related', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('relation_type');
            }

            if (! Schema::hasColumn('product_related', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        DB::table('product_related')->whereNull('relation_type')->update(['relation_type' => 'related']);
        DB::table('product_related')->whereNull('sort_order')->update(['sort_order' => 0]);
        DB::table('product_related')->whereNull('is_active')->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('product_related', function (Blueprint $table) {
            foreach (['is_active', 'sort_order', 'relation_type'] as $column) {
                if (Schema::hasColumn('product_related', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
