@extends('layouts.admin')

@section('title', __('Returns & RMA') . ' | Admin')

@section('content')
<div class="admin-page-shell" data-live-list>
    <x-admin.page-header :kicker="__('Operations')" :title="__('Returns & RMA')" :description="__('Review customer return requests, approve quantities, receive physical items, control restock, and link the final refund or exchange outcome.')" />

    <div class="row g-3 mb-4">
        @foreach([
            ['label'=>__('Requested'),'value'=>$stats['requested'],'icon'=>'mdi-file-clock-outline'],
            ['label'=>__('Approved'),'value'=>$stats['approved'],'icon'=>'mdi-check-decagram-outline'],
            ['label'=>__('Received'),'value'=>$stats['received'],'icon'=>'mdi-package-down'],
            ['label'=>__('Completed'),'value'=>$stats['completed'],'icon'=>'mdi-package-check']
        ] as $card)
            <div class="col-md-6 col-xl-3">
                <div class="admin-card admin-stat-card h-100">
                    <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                    <div class="admin-stat-label">{{ $card['label'] }}</div>
                    <div class="admin-stat-value">{{ $card['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <form method="GET" action="{{ route('admin.returns.index') }}" class="row g-3 align-items-end" data-live-filter>
                <div class="col-lg-5">
                    <label class="form-label fw-semibold">{{ __('Search') }}</label>
                    <input type="search" name="search" data-live-search autocomplete="off" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Return reference, order, customer, or email') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ __('Status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                    <select name="per_page" class="form-select" data-live-filter-control>
                        @foreach([20,40,80] as $size)
                            <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap justify-content-end">
                    <button class="btn btn-primary flex-fill">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.returns.index') }}" class="btn btn-light border flex-fill" data-live-reset>{{ __('Reset') }}</a>
                </div>
            </form>
            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.returns.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.returns._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
