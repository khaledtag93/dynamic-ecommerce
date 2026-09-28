<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_request_items', function (Blueprint $table) {
            $table->dropForeign('rma_item_request_fk');

            $table->foreign('return_request_id', 'rma_item_request_fk')
                ->references('id')
                ->on('return_requests')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_request_items', function (Blueprint $table) {
            $table->dropForeign('rma_item_request_fk');

            $table->foreign('return_request_id', 'rma_item_request_fk')
                ->references('id')
                ->on('return_requests')
                ->cascadeOnDelete();
        });
    }
};
