@extends('layouts.admin')

@section('title', __('Purchases') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Purchases')" :description="__('Track procurement activity, receive stock safely, and keep supplier purchasing history clear.')">
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('New purchase') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Purchase orders'), 'value' => $stats['total'], 'copy' => __('All procurement records.'), 'icon' => 'mdi-clipboard-text-outline'],
            ['label' => __('Awaiting receipt'), 'value' => $stats['awaiting'], 'copy' => __('Orders that still need stock receiving.'), 'icon' => 'mdi-truck-clock-outline'],
            ['label' => __('Received'), 'value' => $stats['received'], 'copy' => __('Purchases already added to inventory.'), 'icon' => 'mdi-package-check'],
            ['label' => __('Procurement value'), 'value' => 'EGP ' . number_format($stats['value'], 2), 'copy' => __('Total recorded purchase value.'), 'icon' => 'mdi-cash-multiple'],
        ] as $card)
            <div class="col-md-6 col-xl-3">
                <x-admin.stat-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :icon="$card['icon']"
                    :help="$card['copy']"
                    class="h-100"
                />
            </div>
        @endforeach
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <form method="GET" action="{{ route('admin.purchases.index') }}" class="row g-3 align-items-end" data-live-filter>
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">{{ __('Search purchases') }}</label>
                    <input type="search" name="search" data-live-search autocomplete="off" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Reference, supplier, or company') }}">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label fw-semibold">{{ __('Status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach(AppModelsPurchase::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label fw-semibold">{{ __('Supplier') }}</label>
                    <select name="supplier_id" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All suppliers') }}</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string)$filters['supplier_id'] === (string)$supplier->id)>{{ $supplier->name }}{{ $supplier->company ? ' · '.$supplier->company : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-1">
                    <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                    <select name="per_page" class="form-select" data-live-filter-control>
                        @foreach([15,30,60] as $size)
                            <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill">{{ __('Apply') }}</button>
                    <a href="{{ route('admin.purchases.index') }}" class="btn btn-light border" data-live-reset>{{ __('Reset') }}</a>
                </div>
            </form>
            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.purchases.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.purchases._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
