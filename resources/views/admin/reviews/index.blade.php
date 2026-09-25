@extends('layouts.admin')

@section('title', __('Product Reviews') . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Customer trust')"
    :title="__('Product Reviews')"
    :description="__('Moderate verified-purchase reviews before they appear on product pages.')"
/>

<div class="admin-page-shell" data-live-list>
    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Total reviews'), 'value' => $stats['total'], 'icon' => 'mdi-message-star-outline', 'tone' => 'primary'],
            ['label' => __('Pending review'), 'value' => $stats['pending'], 'icon' => 'mdi-clock-outline', 'tone' => 'warning'],
            ['label' => __('Approved'), 'value' => $stats['approved'], 'icon' => 'mdi-check-decagram-outline', 'tone' => 'success'],
            ['label' => __('Rejected'), 'value' => $stats['rejected'], 'icon' => 'mdi-close-circle-outline', 'tone' => 'danger'],
        ] as $card)
            <div class="col-sm-6 col-xl-3">
                <x-admin.stat-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :icon="$card['icon']"
                    :tone="$card['tone']"
                    class="h-100"
                />
            </div>
        @endforeach
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <form method="GET" action="{{ route('admin.reviews.index') }}" class="admin-filter-grid" data-live-filter>
                <div>
                    <label class="form-label fw-semibold">{{ __('Search reviews') }}</label>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        class="form-control"
                        placeholder="{{ __('Product, customer, email, or review text') }}"
                        autocomplete="off"
                        data-live-search
                    >
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach(\App\Models\ProductReview::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Rating') }}</label>
                    <select name="rating" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All ratings') }}</option>
                        @foreach([5,4,3,2,1] as $rating)
                            <option value="{{ $rating }}" @selected((string) $filters['rating'] === (string) $rating)>{{ $rating }} / 5</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                    <select name="per_page" class="form-select" data-live-filter-control>
                        @foreach([20,40,80] as $size)
                            <option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                    <a href="{{ route('admin.reviews.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
                </div>
            </form>

            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.reviews.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.reviews._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
