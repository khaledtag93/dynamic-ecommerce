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

        $demoValues = [
            'store_support_email' => ['support@example-store.com'],
            'store_support_phone' => ['+20 100 000 0000'],
            'store_support_whatsapp' => ['+20 100 000 0000'],
            'store_contact_address' => ['Cairo, Egypt'],
            'store_business_website' => ['https://example-store.com'],
            'store_contact_hours' => ['Daily from 10:00 AM to 10:00 PM'],
            'store_contact_hours_en' => ['Daily from 10:00 AM to 10:00 PM'],
            'store_contact_hours_ar' => ['يوميًا من 10:00 صباحًا إلى 10:00 مساءً'],
        ];

        foreach ($demoValues as $key => $values) {
            DB::table('website_settings')
                ->where('key', $key)
                ->whereIn('value', $values)
                ->delete();
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: known demo contact values must not be restored.
    }
};
