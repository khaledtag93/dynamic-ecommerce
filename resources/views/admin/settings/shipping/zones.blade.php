@extends('layouts.admin')

@section('title', __('Shipping zones & cities') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Commerce setup')" :title="__('Shipping zones & cities')" :description="__('Group exact city names into shipping zones. A city can belong to only one zone per country.')">
        <a href="{{ route('admin.settings.shipping.methods') }}" class="btn btn-light border">{{ __('Shipping methods') }}</a>
        <a href="{{ route('admin.settings.shipping.rates') }}" class="btn btn-light border">{{ __('Shipping rates') }}</a>
    </x-admin.page-header>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <h4 class="mb-1">{{ __('Create shipping zone') }}</h4>
            <p class="text-muted small">{{ __('Country uses ISO 2-letter code plus the storefront country name customers enter at checkout.') }}</p>
            <form method="POST" action="{{ route('admin.settings.shipping.zones.store') }}" data-submit-loading>
                @csrf
                <div class="row g-3">
                    <div class="col-md-2"><input name="code" class="form-control" placeholder="{{ __('Code') }}" maxlength="60" required></div>
                    <div class="col-md-2"><input name="name" class="form-control" placeholder="{{ __('English name') }}" maxlength="120" required></div>
                    <div class="col-md-2"><input name="name_ar" dir="rtl" class="form-control" placeholder="{{ __('Arabic name') }}" maxlength="120"></div>
                    <div class="col-md-1"><input name="country_code" class="form-control text-uppercase" placeholder="EG" maxlength="2" required></div>
                    <div class="col-md-2"><input name="country_name" class="form-control" placeholder="{{ __('Country name') }}" maxlength="120" required></div>
                    <div class="col-md-1"><input type="number" name="priority" class="form-control" value="100" min="1" max="9999" required></div>
                    <div class="col-md-1 d-flex align-items-center">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked title="{{ __('Active') }}"></div>
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
                                <div class="col-md-4"><input name="code" class="form-control" value="{{ $zone->code }}" required maxlength="60"></div>
                                <div class="col-md-4"><input name="name" class="form-control" value="{{ $zone->name }}" required maxlength="120"></div>
                                <div class="col-md-4"><input name="name_ar" dir="rtl" class="form-control" value="{{ $zone->name_ar }}" maxlength="120"></div>
                                <div class="col-md-3"><input name="country_code" class="form-control text-uppercase" value="{{ $zone->country_code }}" required maxlength="2"></div>
                                <div class="col-md-5"><input name="country_name" class="form-control" value="{{ $zone->country_name }}" required maxlength="120"></div>
                                <div class="col-md-2"><input type="number" name="priority" class="form-control" value="{{ $zone->priority }}" min="1" max="9999" required></div>
                                <div class="col-md-2 d-flex align-items-center">
                                    <input type="hidden" name="is_active" value="0">
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($zone->is_active)></div>
                                </div>
                                <div class="col-12"><textarea name="notes" rows="2" class="form-control" maxlength="2000" placeholder="{{ __('Notes') }}">{{ $zone->notes }}</textarea></div>
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
                            <input name="name" class="form-control" placeholder="{{ __('Add exact checkout city name') }}" maxlength="120" required>
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
