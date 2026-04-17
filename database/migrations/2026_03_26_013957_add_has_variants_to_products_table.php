<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'has_variants')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'has_variants')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('has_variants');
        });
    }
};
