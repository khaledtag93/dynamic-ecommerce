<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Http\Request;

class ContentSettingsController extends Controller
{
    public function __construct(protected StoreSettingsService $settingsService)
    {
    }

    public function edit()
    {
        $settings = $this->settingsService->all();

        return view('admin.settings.content', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'store_support_email' => ['nullable', 'email', 'max:255'],
            'store_support_phone' => ['nullable', 'string', 'max:100'],
            'store_support_whatsapp' => ['nullable', 'string', 'max:100'],
            'store_contact_address' => ['nullable', 'string', 'max:500'],
            'store_contact_map_url' => ['nullable', 'url', 'max:500'],
            'store_business_website' => ['nullable', 'url', 'max:255'],
            'store_contact_hours_en' => ['nullable', 'string', 'max:500'],
            'store_contact_hours_ar' => ['nullable', 'string', 'max:500'],
            'contact_page_title' => ['nullable', 'string', 'max:255'],
            'contact_page_subtitle' => ['nullable', 'string', 'max:255'],
            'contact_page_intro' => ['nullable', 'string', 'max:2000'],
            'contact_show_email' => ['nullable', 'boolean'],
            'contact_show_phone' => ['nullable', 'boolean'],
            'contact_show_whatsapp' => ['nullable', 'boolean'],
            'contact_show_address' => ['nullable', 'boolean'],
            'contact_show_hours' => ['nullable', 'boolean'],
            'contact_show_map' => ['nullable', 'boolean'],
            'legal_privacy_title' => ['nullable', 'string', 'max:255'],
            'legal_privacy_intro' => ['nullable', 'string', 'max:2000'],
            'legal_privacy_body' => ['nullable', 'string', 'max:20000'],
            'legal_terms_title' => ['nullable', 'string', 'max:255'],
            'legal_terms_intro' => ['nullable', 'string', 'max:2000'],
            'legal_terms_body' => ['nullable', 'string', 'max:20000'],
            'legal_refund_title' => ['nullable', 'string', 'max:255'],
            'legal_refund_intro' => ['nullable', 'string', 'max:2000'],
            'legal_refund_body' => ['nullable', 'string', 'max:20000'],
            'legal_shipping_title' => ['nullable', 'string', 'max:255'],
            'legal_shipping_intro' => ['nullable', 'string', 'max:2000'],
            'legal_shipping_body' => ['nullable', 'string', 'max:20000'],
            'checkout_support_note' => ['nullable', 'string', 'max:1000'],
            'checkout_secure_notice' => ['nullable', 'string', 'max:2000'],
            'checkout_cod_note' => ['nullable', 'string', 'max:1000'],
            'checkout_bank_transfer_note' => ['nullable', 'string', 'max:1000'],
            'checkout_online_note' => ['nullable', 'string', 'max:1000'],
            'orders_allow_customer_cancellation' => ['nullable', 'boolean'],
            'orders_customer_cancellation_note' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ([
            'contact_show_email', 'contact_show_phone', 'contact_show_whatsapp', 'contact_show_address', 'contact_show_hours', 'contact_show_map',
            'orders_allow_customer_cancellation',
        ] as $booleanKey) {
            WebsiteSetting::setValue($booleanKey, $request->boolean($booleanKey) ? '1' : '0', 'content');
        }

        foreach ($data as $key => $value) {
            if (in_array($key, ['contact_show_email', 'contact_show_phone', 'contact_show_whatsapp', 'contact_show_address', 'contact_show_hours', 'contact_show_map', 'orders_allow_customer_cancellation'], true)) {
                continue;
            }

            WebsiteSetting::setValue($key, $value, 'content');
        }

        return back()->with('success', __('Store content settings updated successfully.'));
    }
}
