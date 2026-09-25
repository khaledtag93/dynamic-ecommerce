<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    public function edit()
    {
        return view('admin/settings/payments');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_cod_enabled' => ['nullable', 'boolean'],
            'payment_bank_transfer_enabled' => ['nullable', 'boolean'],
            'payment_online_enabled' => ['nullable', 'boolean'],
            'payment_gateway_provider' => ['nullable', 'string', 'max:120'],
            'payment_gateway_mode' => ['nullable', 'string', 'max:50'],
            'payment_stock_reservation_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'payment_gateway_public_key' => ['nullable', 'string', 'max:255'],
            'paymob_integration_id' => ['nullable', 'string', 'max:255'],
            'paymob_iframe_id' => ['nullable', 'string', 'max:255'],
            'bank_transfer_instructions_en' => ['nullable', 'string', 'max:3000'],
            'bank_transfer_instructions_ar' => ['nullable', 'string', 'max:3000'],
        ]);

        $booleanKeys = [
            'payment_cod_enabled',
            'payment_bank_transfer_enabled',
            'payment_online_enabled',
        ];

        foreach ($booleanKeys as $key) {
            WebsiteSetting::setValue($key, $request->boolean($key) ? '1' : '0', 'payment');
        }

        foreach (array_diff(array_keys($validated), $booleanKeys) as $key) {
            WebsiteSetting::setValue($key, $validated[$key] ?? null, 'payment');
        }

        WebsiteSetting::query()
            ->whereIn('key', StoreSettingsService::sensitiveSettingKeys())
            ->delete();

        return back()->with('success', __('Payment settings updated successfully.'));
    }
}
