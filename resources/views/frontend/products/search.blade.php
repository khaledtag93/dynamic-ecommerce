@extends('layouts.app')

@section('title', ($filters['q'] ? __('Search results for :query', ['query' => $filters['q']]) : __('Browse products')) . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 storefront-search-page" data-live-list>
    <div class="container">
        <x-frontend.page-hero
            :eyebrow="__('Product search')"
            :title="$filters['q'] ? __('Search results for :query', ['query' => $filters['q']]) : __('Browse products')"
            :description="__('Search the full catalog, then narrow results by availability, offers, or price order.')"
            class="p-4 p-lg-5 mb-4"
        >
            <a href="{{ route('frontend.home') }}" class="btn lc-btn-soft">
                <i class="bi bi-arrow-left me-2"></i>{{ __('Back to home') }}
            </a>
        </x-frontend.page-hero>

        <div class="lc-card p-3 p-lg-4 mb-4 storefront-search-filters">
            <form method="GET" action="{{ route('frontend.search') }}" class="row g-3 align-items-end" data-live-filter>
                <div class="col-lg-5">
                    <label class="form-label fw-bold" for="catalogSearch">{{ __('Search products') }}</label>
                    <div class="position-relative">
                        <i class="bi bi-search storefront-search-icon"></i>
                        <input
                            id="catalogSearch"
                            type="search"
                            name="q"
                            data-live-search
                            autocomplete="off"
                            value="{{ $filters['q'] }}"
                            class="form-control lc-form-control ps-5"
                            placeholder="{{ __('Product name, description, or keyword') }}"
                        >
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="form-label fw-bold">{{ __('Availability') }}</label>
                    <select name="availability" class="form-select lc-form-select" data-live-filter-control>
                        <option value="all" @selected($filters['availability'] === 'all')>{{ __('All products') }}</option>
                        <option value="in_stock" @selected($filters['availability'] === 'in_stock')>{{ __('In stock only') }}</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="form-label fw-bold">{{ __('Offers') }}</label>
                    <select name="offer" class="form-select lc-form-select" data-live-filter-control>
                        <option value="all" @selected($filters['offer'] === 'all')>{{ __('All offers') }}</option>
                        <option value="on_sale" @selected($filters['offer'] === 'on_sale')>{{ __('Discounted only') }}</option>
                    </select>
                </div>

                <div class="col-sm-7 col-lg-2">
                    <label class="form-label fw-bold">{{ __('Sort by') }}</label>
                    <select name="sort" class="form-select lc-form-select" data-live-filter-control>
                        <option value="latest" @selected($filters['sort'] === 'latest')>{{ __('Newest first') }}</option>
                        <option value="price_low_high" @selected($filters['sort'] === 'price_low_high')>{{ __('Price: low to high') }}</option>
                        <option value="price_high_low" @selected($filters['sort'] === 'price_high_low')>{{ __('Price: high to low') }}</option>
                        <option value="name_az" @selected($filters['sort'] === 'name_az')>{{ __('Name: A to Z') }}</option>
                    </select>
                </div>

                <div class="col-sm-5 col-lg-1 d-grid">
                    <button type="submit" class="btn lc-btn-primary px-3" aria-label="{{ __('Apply search filters') }}"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('frontend.search') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>

        @include('frontend.products._search_results')
    </div>
</section>
@endsection

@push('styles')
<style>
.storefront-search-page{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-soft) 24%,#fff),#fff 28%)}
.storefront-search-filters{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-surface) 98%,transparent),color-mix(in srgb,var(--lc-soft) 45%,var(--lc-surface)))}
.storefront-search-icon{position:absolute;top:50%;transform:translateY(-50%);left:1rem;color:var(--lc-muted);pointer-events:none}
body[dir="rtl"] .storefront-search-icon{left:auto;right:1rem}
.storefront-search-summary{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.storefront-search-empty{border-style:dashed}
.storefront-search-pagination nav{display:flex;justify-content:center}
.storefront-search-pagination .pagination{gap:.45rem;flex-wrap:wrap}
.storefront-search-pagination .page-link{border:none;border-radius:.9rem;padding:.72rem .95rem;color:var(--lc-primary-dark);background:color-mix(in srgb,var(--lc-soft) 72%,#fff);font-weight:800;box-shadow:none}
.storefront-search-pagination .page-item.active .page-link{background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff}
@media(max-width:767.98px){.storefront-search-summary{align-items:flex-start;flex-direction:column}.storefront-search-summary .btn{width:100%}}
</style>
@endpush

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
