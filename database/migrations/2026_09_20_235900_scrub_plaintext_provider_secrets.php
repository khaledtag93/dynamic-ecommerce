<?php

use App\Services\Commerce\StoreSettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_settings')) {
            return;
        }

        DB::table('website_settings')
            ->whereIn('key', StoreSettingsService::sensitiveSettingKeys())
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: plaintext provider credentials must not be restored.
    }
};
