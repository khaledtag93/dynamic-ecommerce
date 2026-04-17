<?php

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class WebsiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'project_name' => 'Tag Marketplace',
            'store_name' => 'Tag Market Place',
            'store_name_ar' => 'تاج ماركت بليس',
            'store_name_en' => 'Tag Market Place',
            'store_tagline' => 'A scalable commerce foundation for modern stores.',
            'store_tagline_ar' => 'بنية تجارة إلكترونية قابلة للتوسع لمتاجر عصرية.',
            'store_tagline_en' => 'A scalable commerce foundation for modern stores.',
            'footer_about' => 'A clean, scalable storefront built to look production-ready from day one.',
            'footer_about_ar' => 'واجهة متجر نظيفة وقابلة للتوسع وتبدو جاهزة للإنتاج من أول يوم.',
            'footer_about_en' => 'A clean, scalable storefront built to look production-ready from day one.',
            'footer_copyright' => 'All rights reserved.',
            'footer_copyright_ar' => 'جميع الحقوق محفوظة.',
            'footer_copyright_en' => 'All rights reserved.',
            'default_locale' => 'ar',
            'store_support_email' => 'support@example-store.com',
            'store_support_phone' => '+20 100 000 0000',
            'store_support_whatsapp' => '+20 100 000 0000',
            'store_contact_address' => 'Cairo, Egypt',
            'store_business_website' => 'https://example-store.com',
            'contact_page_title' => 'Contact us',
            'contact_page_title_ar' => 'تواصل معنا',
            'contact_page_title_en' => 'Contact us',
            'contact_page_subtitle' => 'Reach our team for orders, payments, shipping, or product support.',
            'contact_page_subtitle_ar' => 'تواصل مع فريقنا بخصوص الطلبات والدفع والشحن ودعم المنتجات.',
            'contact_page_subtitle_en' => 'Reach our team for orders, payments, shipping, or product support.',
            'contact_page_intro' => 'Use the channels below for order follow-up, payment issues, returns, delivery help, or general questions.',
            'contact_page_intro_ar' => 'استخدم الوسائل التالية لمتابعة الطلبات أو مشكلات الدفع أو الاسترجاع أو المساعدة في التوصيل أو أي استفسار عام.',
            'contact_page_intro_en' => 'Use the channels below for order follow-up, payment issues, returns, delivery help, or general questions.',
        ];

        foreach ($settings as $key => $value) {
            WebsiteSetting::setValue($key, $value, 'branding');
        }
    }
}
