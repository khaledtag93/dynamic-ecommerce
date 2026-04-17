<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_related')) {
            return;
        }

        Schema::table('product_related', function (Blueprint $table) {
            try {
                $table->dropForeign(['product_id']);
            } catch (\Throwable $e) {
            }

            try {
                $table->dropForeign(['related_product_id']);
            } catch (\Throwable $e) {
            }
        });

        try {
            DB::statement('ALTER TABLE `product_related` DROP INDEX `product_related_product_id_related_product_id_unique`');
        } catch (\Throwable $e) {
        }

        Schema::table('product_related', function (Blueprint $table) {
            try {
                $table->unique(['product_id', 'related_product_id', 'relation_type'], 'product_related_product_related_type_unique');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('product_related', function (Blueprint $table) {
            try {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            } catch (\Throwable $e) {
            }

            try {
                $table->foreign('related_product_id')->references('id')->on('products')->cascadeOnDelete();
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_related')) {
            return;
        }

        Schema::table('product_related', function (Blueprint $table) {
            try {
                $table->dropForeign(['product_id']);
            } catch (\Throwable $e) {
            }

            try {
                $table->dropForeign(['related_product_id']);
            } catch (\Throwable $e) {
            }
        });

        try {
            DB::statement('ALTER TABLE `product_related` DROP INDEX `product_related_product_related_type_unique`');
        } catch (\Throwable $e) {
        }

        Schema::table('product_related', function (Blueprint $table) {
            try {
                $table->unique(['product_id', 'related_product_id'], 'product_related_product_id_related_product_id_unique');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('product_related', function (Blueprint $table) {
            try {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            } catch (\Throwable $e) {
            }

            try {
                $table->foreign('related_product_id')->references('id')->on('products')->cascadeOnDelete();
            } catch (\Throwable $e) {
            }
        });
    }
};
