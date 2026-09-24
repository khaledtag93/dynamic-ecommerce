<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->string('hold_label', 80)->nullable()->after('open_token');
            $table->timestamp('held_at')->nullable()->after('hold_label')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->dropIndex(['held_at']);
            $table->dropColumn(['hold_label', 'held_at']);
        });
    }
};
