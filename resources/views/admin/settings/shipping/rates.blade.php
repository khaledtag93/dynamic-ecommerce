@extends('layouts.admin')

@section('title', __('Shipping rates') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Commerce setup')" :title="__('Shipping rates')" :description="__('Set one server-authoritative rate per shipping method and zone. Free-shipping thresholds are optional and require an explicit calculation basis.')">
        <a href="{{ route('admin.settings.shipping.methods') }}" class="btn btn-light border">{{ __('Shipping methods') }}</a>
        <a href="{{ route('admin.settings.shipping.zones') }}" class="btn btn-light border">{{ __('Zones & cities') }}</a>
    </x-admin.page-header>

    <div class="alert alert-info border-0 rounded-4">
        {{ __('Store pickup does not need a rate and is always quoted as zero shipping. Standard and Express shipping require an active zone rate before checkout can use them.') }}
    </div>

    @forelse($zones as $zone)
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="admin-table-toolbar">
                    <div>
                        <h4 class="mb-1">{{ $zone->displayName() }}</h4>
                        <div class="text-muted small">{{ $zone->country_name }} · {{ $zone->country_code }}</div>
                    </div>
                </div>

                <div class="row g-3">
                    @foreach($methods as $method)
                        @php($rate = $rates->get($zone->id . ':' . $method->id))
                        <div class="col-xl-6">
                            <form method="POST" action="{{ route('admin.settings.shipping.rates.upsert') }}" class="border rounded-4 p-3 h-100" data-submit-loading>
                                @csrf
                                <input type="hidden" name="shipping_zone_id" value="{{ $zone->id }}">
                                <input type="hidden" name="shipping_method_id" value="{{ $method->id }}">
                                <div class="d-flex justify-content-between gap-3 mb-3">
                                    <div><div class="fw-bold">{{ $method->displayName() }}</div><div class="text-muted small">{{ $method->code }}</div></div>
                                    <div>
                                        <input type="hidden" name="is_active" value="0">
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($rate?->is_active ?? true)></div>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">{{ __('Rate amount') }}</label>
                                        <input type="number" name="amount" min="0" step="0.01" class="form-control" value="{{ $rate?->amount ?? '' }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">{{ __('Free threshold') }}</label>
                                        <input type="number" name="free_shipping_threshold" min="0.01" step="0.01" class="form-control" value="{{ $rate?->free_shipping_threshold ?? '' }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">{{ __('Threshold basis') }}</label>
                                        <select name="threshold_basis" class="form-select">
                                            <option value="">{{ __('Not applicable') }}</option>
                                            @foreach(AppModelsShippingRate::thresholdBasisOptions() as $value => $label)
                                                <option value="{{ $value }}" @selected($rate?->threshold_basis === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100 mt-3">{{ $rate ? __('Update rate') : __('Create rate') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="admin-card"><div class="admin-card-body text-center text-muted py-5">{{ __('Create an active shipping zone before configuring rates.') }}</div></div>
    @endforelse
</div>
@endsection
