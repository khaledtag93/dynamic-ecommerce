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
            'store_tagline' => ['Original electronics, trusted checkout, and fast delivery.'],
            'store_tagline_en' => ['Original electronics, trusted checkout, and fast delivery.'],
            'store_tagline_ar' => ['أجهزة إلكترونية أصلية، دفع آمن، وتوصيل سريع.'],
            'footer_about' => ['Shop phones, laptops, gaming, audio, TVs, accessories, and smart devices with clear prices and trusted support.'],
            'footer_about_en' => ['Shop phones, laptops, gaming, audio, TVs, accessories, and smart devices with clear prices and trusted support.'],
            'footer_about_ar' => ['تسوّق الموبايلات واللابتوبات والألعاب والسماعات والشاشات والإكسسوارات بأسعار واضحة ودعم موثوق.'],
            'hero_title' => ['Latest electronics and smart devices in one trusted store.'],
            'hero_title_en' => ['Latest electronics and smart devices in one trusted store.'],
            'hero_title_ar' => ['أحدث الأجهزة الإلكترونية والذكية في متجر واحد موثوق.'],
            'hero_subtitle' => ['Shop phones, laptops, gaming gear, TVs, audio, and accessories with clear offers, secure checkout, and fast delivery.'],
            'hero_subtitle_en' => ['Shop phones, laptops, gaming gear, TVs, audio, and accessories with clear offers, secure checkout, and fast delivery.'],
            'hero_subtitle_ar' => ['تسوّق الموبايلات واللابتوبات وأجهزة الألعاب والشاشات والسماعات والإكسسوارات بعروض واضحة ودفع آمن وتوصيل سريع.'],
            'hero_badge_text' => ['Electronics deals'],
            'hero_badge_text_en' => ['Electronics deals'],
            'hero_badge_text_ar' => ['عروض الإلكترونيات'],
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
