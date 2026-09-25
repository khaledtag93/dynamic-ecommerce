<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCity;
use App\Services\Commerce\AdminActivityLogService;
use App\Services\Commerce\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShippingSettingsController extends Controller
{
    public function __construct(
        protected ShippingService $shippingService,
        protected AdminActivityLogService $activityLogService,
    ) {
    }

    public function methods()
    {
        return view('admin.settings.shipping.methods', [
            'methods' => ShippingMethod::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function updateMethod(Request $request, ShippingMethod $shippingMethod)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'eta_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'eta_max_days' => ['nullable', 'integer', 'min:0', 'max:365', 'gte:eta_min_days'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shippingMethod->update([
            'name' => trim($data['name']),
            'name_ar' => $this->nullableTrim($data['name_ar'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $data['sort_order'],
            'eta_min_days' => $data['eta_min_days'] ?? null,
            'eta_max_days' => $data['eta_max_days'] ?? null,
            'notes' => $this->nullableTrim($data['notes'] ?? null),
        ]);

        $this->activityLogService->log(
            'commerce',
            'shipping_method_updated',
            __('Shipping method updated.'),
            $request->user()?->id,
            $shippingMethod,
            ['code' => $shippingMethod->code]
        );

        return back()->with('success', __('Shipping method updated.'));
    }

    public function zones()
    {
        return view('admin.settings.shipping.zones', [
            'zones' => ShippingZone::query()
                ->with(['cities' => fn ($query) => $query->orderBy('name')])
                ->withCount('rates')
                ->orderBy('priority')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeZone(Request $request)
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
            'country_code' => Str::upper(trim((string) $request->input('country_code'))),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('shipping_zones', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'country_name' => ['required', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $zone = ShippingZone::query()->create([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->activityLogService->log(
            'commerce',
            'shipping_zone_created',
            __('Shipping zone created.'),
            $request->user()?->id,
            $zone,
            ['code' => $zone->code]
        );

        return back()->with('success', __('Shipping zone created.'));
    }

    public function updateZone(Request $request, ShippingZone $shippingZone)
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
            'country_code' => Str::upper(trim((string) $request->input('country_code'))),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('shipping_zones', 'code')->ignore($shippingZone->id)],
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'country_name' => ['required', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shippingZone->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        $shippingZone->cities()->update(['country_code' => $shippingZone->country_code]);

        $this->activityLogService->log(
            'commerce',
            'shipping_zone_updated',
            __('Shipping zone updated.'),
            $request->user()?->id,
            $shippingZone,
            ['code' => $shippingZone->code]
        );

        return back()->with('success', __('Shipping zone updated.'));
    }

    public function storeCity(Request $request, ShippingZone $shippingZone)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);
        $normalized = $this->shippingService->normalizeCity($name);

        if ($normalized === '') {
            return back()->withErrors(['name' => __('Enter a valid city name.')]);
        }

        $city = ShippingZoneCity::query()->create([
            'shipping_zone_id' => $shippingZone->id,
            'country_code' => $shippingZone->country_code,
            'name' => $name,
            'normalized_name' => $normalized,
        ]);

        $this->activityLogService->log(
            'commerce',
            'shipping_zone_city_created',
            __('Shipping city added.'),
            $request->user()?->id,
            $city,
            ['zone_id' => $shippingZone->id]
        );

        return back()->with('success', __('Shipping city added.'));
    }

    public function destroyCity(Request $request, ShippingZoneCity $shippingZoneCity)
    {
        $meta = [
            'zone_id' => $shippingZoneCity->shipping_zone_id,
            'city' => $shippingZoneCity->name,
        ];

        $shippingZoneCity->delete();

        $this->activityLogService->log(
            'commerce',
            'shipping_zone_city_removed',
            __('Shipping city removed.'),
            $request->user()?->id,
            null,
            $meta
        );

        return back()->with('success', __('Shipping city removed.'));
    }

    public function rates()
    {
        return view('admin.settings.shipping.rates', [
            'zones' => ShippingZone::query()
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('name')
                ->get(),
            'methods' => ShippingMethod::query()
                ->where('type', ShippingMethod::TYPE_SHIPPING)
                ->orderBy('sort_order')
                ->get(),
            'rates' => ShippingRate::query()
                ->with(['zone', 'method'])
                ->orderBy('shipping_zone_id')
                ->orderBy('shipping_method_id')
                ->get()
                ->keyBy(fn (ShippingRate $rate) => $rate->shipping_zone_id . ':' . $rate->shipping_method_id),
        ]);
    }

    public function upsertRate(Request $request)
    {
        $threshold = $request->input('free_shipping_threshold');

        $data = $request->validate([
            'shipping_zone_id' => ['required', 'integer', Rule::exists('shipping_zones', 'id')],
            'shipping_method_id' => [
                'required',
                'integer',
                Rule::exists('shipping_methods', 'id')->where(fn ($query) => $query->where('type', ShippingMethod::TYPE_SHIPPING)),
            ],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            'threshold_basis' => [
                Rule::requiredIf($threshold !== null && $threshold !== ''),
                'nullable',
                Rule::in(array_keys(ShippingRate::thresholdBasisOptions())),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (($threshold === null || $threshold === '') && ! empty($data['threshold_basis'])) {
            $data['threshold_basis'] = null;
        }

        $rate = ShippingRate::query()->updateOrCreate(
            [
                'shipping_zone_id' => (int) $data['shipping_zone_id'],
                'shipping_method_id' => (int) $data['shipping_method_id'],
            ],
            [
                'amount' => $data['amount'],
                'free_shipping_threshold' => $data['free_shipping_threshold'] ?? null,
                'threshold_basis' => $data['threshold_basis'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]
        );

        $this->activityLogService->log(
            'commerce',
            'shipping_rate_saved',
            __('Shipping rate saved.'),
            $request->user()?->id,
            $rate,
            [
                'zone_id' => $rate->shipping_zone_id,
                'method_id' => $rate->shipping_method_id,
            ]
        );

        return back()->with('success', __('Shipping rate saved.'));
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
