@extends('layouts.admin')

@section('title', __('Deliveries') . ' | Admin')

@section('content')
<div class="admin-page-shell" data-live-list>
<x-admin.page-header :kicker="__('Operations')" :title="__('Deliveries')" :description="__('Delivery tracking foundation with status, courier, ETA, and tracking number support.')" />

<div class="row g-3 mb-4">
    @foreach([
        ['label'=>__('Total deliveries'),'value'=>$stats['total'],'icon'=>'mdi-truck-outline'],
        ['label'=>__('Needs action'),'value'=>$stats['action'],'icon'=>'mdi-alert-circle-outline'],
        ['label'=>__('In transit'),'value'=>$stats['transit'],'icon'=>'mdi-truck-fast-outline'],
        ['label'=>__('Delivered'),'value'=>$stats['delivered'],'icon'=>'mdi-package-variant-closed-check'],
        ['label'=>__('Exceptions'),'value'=>$stats['exceptions'],'icon'=>'mdi-alert-octagon-outline']
    ] as $card)
        <div class="col-md-6 col-xl">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                <div class="admin-stat-label">{{ $card['label'] }}</div>
                <div class="admin-stat-value">{{ $card['value'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card mb-4"><div class="admin-card-body">
    <form method="GET" action="{{ route('admin.deliveries.index') }}" class="row g-3 align-items-end" data-live-filter>
        <div class="col-lg-4">
            <label class="form-label fw-semibold">{{ __('Search') }}</label>
            <input type="search" name="search" data-live-search autocomplete="off" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Order, customer, tracking, courier') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">{{ __('Delivery status') }}</label>
            <select name="delivery_status" class="form-select" data-live-filter-control>
                <option value="">{{ __('All statuses') }}</option>
                @foreach($deliveryStatusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filters['delivery_status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">{{ __('Delivery method') }}</label>
            <select name="delivery_method" class="form-select" data-live-filter-control>
                <option value="">{{ __('All methods') }}</option>
                @foreach($deliveryMethodOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filters['delivery_method'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold">{{ __('Per page') }}</label>
            <select name="per_page" class="form-select" data-live-filter-control>
                @foreach([15,30,60] as $size)
                    <option value="{{ $size }}" @selected((int)$filters['per_page']===$size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="queue" value="{{ $filters['queue'] }}">
        <div class="col-12 d-flex gap-2 flex-wrap justify-content-end">
            <button type="submit" class="btn btn-primary flex-fill">{{ __('Filter') }}</button>
            <a href="{{ route('admin.deliveries.index') }}" class="btn btn-light border flex-fill" data-live-reset>{{ __('Reset') }}</a>
        </div>
    </form>
    <div class="small mt-2" role="status" aria-live="polite" data-live-status
         data-loading="{{ __('Updating results...') }}"
         data-updated="{{ __('Results updated.') }}"
         data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
    <a href="{{ route('admin.deliveries.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
</div></div>

@include('admin.deliveries._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
