@extends('layouts.admin')

@section('title', __('Categories') . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Catalog management')"
    :title="__('Categories')"
    :description="__('Organize the storefront structure, monitor visibility, and keep category dependencies under control.')"
>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add category') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total categories'), 'value' => $stats['total'], 'copy' => __('All category records in the store.'), 'icon' => 'mdi-shape-outline'],
        ['label' => __('Visible'), 'value' => $stats['visible'], 'copy' => __('Customer-facing categories that can be browsed.'), 'icon' => 'mdi-eye-outline'],
        ['label' => __('Hidden'), 'value' => $stats['hidden'], 'copy' => __('Temporarily hidden categories.'), 'icon' => 'mdi-eye-off-outline'],
        ['label' => __('With products'), 'value' => $stats['with_products'], 'copy' => __('Categories already linked to products.'), 'icon' => 'mdi-package-variant'],
        ['label' => __('Empty categories'), 'value' => $stats['empty'], 'copy' => __('Categories with no linked products yet.'), 'icon' => 'mdi-package-variant-closed'],
        ['label' => __('Needs content'), 'value' => $stats['needs_content'], 'copy' => __('Missing a description or category image.'), 'icon' => 'mdi-text-box-search-outline'],
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
        <form method="GET" action="{{ route('admin.categories.index') }}" class="admin-filter-grid admin-filter-grid-categories" data-live-filter>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="search" name="search" data-live-search autocomplete="off" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Category name, slug, or description') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Visibility') }}</label>
                <select name="visibility" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All') }}</option>
                    <option value="visible" @selected($filters['visibility'] === 'visible')>{{ __('Visible') }}</option>
                    <option value="hidden" @selected($filters['visibility'] === 'hidden')>{{ __('Hidden') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Product usage') }}</label>
                <select name="usage" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All categories') }}</option>
                    <option value="used" @selected($filters['usage'] === 'used')>{{ __('With products') }}</option>
                    <option value="empty" @selected($filters['usage'] === 'empty')>{{ __('Empty categories') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Content readiness') }}</label>
                <select name="readiness" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All content states') }}</option>
                    <option value="needs_content" @selected($filters['readiness'] === 'needs_content')>{{ __('Needs content') }}</option>
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'updated_at' }}" data-live-default="updated_at">
            <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}" data-live-default="desc">
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.categories.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>

@include('admin.category._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
