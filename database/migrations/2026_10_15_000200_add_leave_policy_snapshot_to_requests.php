<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leave_requests', function (Blueprint $table) {
            // Existing requests are left NULL: their former policy cannot be
            // reconstructed reliably from the leave type's CURRENT settings.
            $table->json('policy_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_requests', function (Blueprint $table) {
            $table->dropColumn('policy_snapshot');
        });
    }
};
