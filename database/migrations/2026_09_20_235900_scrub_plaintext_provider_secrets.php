<?php

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
            ->whereIn('key', [
                'payment_gateway_secret_key',
                'payment_gateway_webhook_secret',
                'paymob_api_key',
                'paymob_hmac_secret',
                'whatsapp_meta_access_token',
                'whatsapp_meta_app_secret',
                'whatsapp_meta_verify_token',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: plaintext provider credentials must not be restored.
    }
};
