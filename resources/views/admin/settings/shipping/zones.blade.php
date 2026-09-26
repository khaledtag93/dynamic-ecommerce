@extends('layouts.admin')

@section('title', __('Shipping zones & cities') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Commerce setup')" :title="__('Shipping zones & cities')" :description="__('Group exact city names into shipping zones. A city can belong to only one zone per country.')">
    </x-admin.page-header>

    @include('admin.settings.shipping._nav', ['shippingSection' => 'zones'])

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <h4 class="mb-1">{{ __('Create shipping zone') }}</h4>
            <p class="text-muted small">{{ __('Country uses ISO 2-letter code plus the storefront country name customers enter at checkout.') }}</p>
            <form method="POST" action="{{ route('admin.settings.shipping.zones.store') }}" data-submit-loading>
                @csrf
                <div class="row g-3">
                    <div class="col-md-2"><label class="visually-hidden" for="newShippingZoneCode">{{ __('Code') }}</label><input id="newShippingZoneCode" name="code" aria-required="true" class="form-control" placeholder="{{ __('Code') }}" maxlength="60" required></div>
                    <div class="col-md-2"><label class="visually-hidden" for="newShippingZoneName">{{ __('English name') }}</label><input id="newShippingZoneName" name="name" aria-required="true" class="form-control" placeholder="{{ __('English name') }}" maxlength="120" required></div>
                    <div class="col-md-2"><label class="visually-hidden" for="newShippingZoneNameAr">{{ __('Arabic name') }}</label><input id="newShippingZoneNameAr" name="name_ar" dir="rtl" class="form-control" placeholder="{{ __('Arabic name') }}" maxlength="120"></div>
                    <div class="col-md-1"><label class="visually-hidden" for="newShippingZoneCountryCode">{{ __('Country code') }}</label><input id="newShippingZoneCountryCode" name="country_code" aria-required="true" class="form-control text-uppercase" placeholder="EG" maxlength="2" required></div>
                    <div class="col-md-2"><label class="visually-hidden" for="newShippingZoneCountryName">{{ __('Country name') }}</label><input id="newShippingZoneCountryName" name="country_name" aria-required="true" class="form-control" placeholder="{{ __('Country name') }}" maxlength="120" required></div>
                    <div class="col-md-1"><label class="visually-hidden" for="newShippingZonePriority">{{ __('Priority') }}</label><input id="newShippingZonePriority" type="number" name="priority" aria-required="true" class="form-control" value="100" min="1" max="9999" required></div>
                    <div class="col-md-1 d-flex align-items-center">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="newShippingZoneActive" name="is_active" value="1" checked><label class="visually-hidden" for="newShippingZoneActive">{{ __('Active') }}</label></div>
                    </div>
                    <div class="col-md-1"><button class="btn btn-primary w-100">{{ __('Add') }}</button></div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        @forelse($zones as $zone)
            <div class="col-xl-6">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <form method="POST" action="{{ route('admin.settings.shipping.zones.update', $zone) }}" data-submit-loading>
                            @csrf
                            @method('PUT')
                            <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
                                <div>
                                    <div class="admin-inline-label">{{ $zone->code }} · {{ $zone->country_code }}</div>
                                    <h4 class="mb-1">{{ $zone->displayName() }}</h4>
                                    <div class="text-muted small">{{ trans_choice(':count city|:count cities', $zone->cities->count(), ['count' => $zone->cities->count()]) }} · {{ trans_choice(':count rate|:count rates', $zone->rates_count, ['count' => $zone->rates_count]) }}</div>
                                </div>
                                <span class="badge admin-status-badge {{ $zone->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $zone->is_active ? __('Active') : __('Inactive') }}</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4"><label class="visually-hidden" for="shippingZoneCode-{{ $zone->id }}">{{ __('Code') }}</label><input id="shippingZoneCode-{{ $zone->id }}" name="code" aria-required="true" class="form-control" value="{{ $zone->code }}" required maxlength="60"></div>
                                <div class="col-md-4"><label class="visually-hidden" for="shippingZoneName-{{ $zone->id }}">{{ __('English name') }}</label><input id="shippingZoneName-{{ $zone->id }}" name="name" aria-required="true" class="form-control" value="{{ $zone->name }}" required maxlength="120"></div>
                                <div class="col-md-4"><label class="visually-hidden" for="shippingZoneNameAr-{{ $zone->id }}">{{ __('Arabic name') }}</label><input id="shippingZoneNameAr-{{ $zone->id }}" name="name_ar" dir="rtl" class="form-control" value="{{ $zone->name_ar }}" maxlength="120"></div>
                                <div class="col-md-3"><label class="visually-hidden" for="shippingZoneCountryCode-{{ $zone->id }}">{{ __('Country code') }}</label><input id="shippingZoneCountryCode-{{ $zone->id }}" name="country_code" aria-required="true" class="form-control text-uppercase" value="{{ $zone->country_code }}" required maxlength="2"></div>
                                <div class="col-md-5"><label class="visually-hidden" for="shippingZoneCountryName-{{ $zone->id }}">{{ __('Country name') }}</label><input id="shippingZoneCountryName-{{ $zone->id }}" name="country_name" aria-required="true" class="form-control" value="{{ $zone->country_name }}" required maxlength="120"></div>
                                <div class="col-md-2"><label class="visually-hidden" for="shippingZonePriority-{{ $zone->id }}">{{ __('Priority') }}</label><input id="shippingZonePriority-{{ $zone->id }}" type="number" name="priority" aria-required="true" class="form-control" value="{{ $zone->priority }}" min="1" max="9999" required></div>
                                <div class="col-md-2 d-flex align-items-center">
                                    <input type="hidden" name="is_active" value="0">
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="shippingZoneActive-{{ $zone->id }}" name="is_active" value="1" @checked($zone->is_active)><label class="visually-hidden" for="shippingZoneActive-{{ $zone->id }}">{{ __('Active') }}</label></div>
                                </div>
                                <div class="col-12"><label class="visually-hidden" for="shippingZoneNotes-{{ $zone->id }}">{{ __('Notes') }}</label><textarea id="shippingZoneNotes-{{ $zone->id }}" name="notes" rows="2" class="form-control" maxlength="2000" placeholder="{{ __('Notes') }}">{{ $zone->notes }}</textarea></div>
                            </div>
                            <button class="btn btn-sm btn-light border mt-3">{{ __('Save zone') }}</button>
                        </form>

                        <hr>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach($zone->cities as $city)
                                <form method="POST" action="{{ route('admin.settings.shipping.cities.destroy', $city) }}" class="d-inline" data-confirm-message="{{ __('Remove this city from the shipping zone?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="admin-chip border-0" type="submit">{{ $city->name }} ×</button>
                                </form>
                            @endforeach
                        </div>

                        <form method="POST" action="{{ route('admin.settings.shipping.cities.store', $zone) }}" class="d-flex gap-2" data-submit-loading>
                            @csrf
                            <label class="visually-hidden" for="shippingZoneCity-{{ $zone->id }}">{{ __('Add exact checkout city name') }}</label><input id="shippingZoneCity-{{ $zone->id }}" name="name" class="form-control" placeholder="{{ __('Add exact checkout city name') }}" maxlength="120" required aria-required="true">
                            <button class="btn btn-primary">{{ __('Add city') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="admin-card"><div class="admin-card-body text-center text-muted py-5">{{ __('No shipping zones configured yet.') }}</div></div></div>
        @endforelse
    </div>
</div>
@endsection
