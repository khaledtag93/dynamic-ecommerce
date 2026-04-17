@extends('layouts.admin')

@section('title', __('Categories') . ' | Admin')

@section('content')
@php
    $sort = $filters['sort'] ?? 'updated_at';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'visibility' => $filters['visibility'] ?? null,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.categories.index', $query);
    };
@endphp
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Catalog management') }}</div>
        <h1 class="admin-page-title">{{ __('Categories') }}</h1>
        <p class="admin-page-description">{{ __('Organize the storefront structure, monitor visibility, and keep category dependencies under control.') }}</p>
    </div>
    <div class="admin-page-actions">
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add category') }}</span></a>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total categories'), 'value' => $stats['total'], 'copy' => __('All category records in the store.'), 'icon' => 'mdi-shape-outline'],
        ['label' => __('Visible'), 'value' => $stats['visible'], 'copy' => __('Customer-facing categories that can be browsed.'), 'icon' => 'mdi-eye-outline'],
        ['label' => __('Hidden'), 'value' => $stats['hidden'], 'copy' => __('Temporarily hidden categories.'), 'icon' => 'mdi-eye-off-outline'],
        ['label' => __('With products'), 'value' => $stats['with_products'], 'copy' => __('Categories already linked to products.'), 'icon' => 'mdi-package-variant'],
    ] as $card)
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                <div class="admin-stat-label">{{ $card['label'] }}</div>
                <div class="admin-stat-value">{{ $card['value'] }}</div>
                <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" class="admin-filter-grid admin-filter-grid-categories" data-submit-loading>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Category name, slug, or description') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Visibility') }}</label>
                <select name="visibility" class="form-select">
                    <option value="">{{ __('All') }}</option>
                    <option value="visible" @selected($filters['visibility'] === 'visible')>{{ __('Visible') }}</option>
                    <option value="hidden" @selected($filters['visibility'] === 'hidden')>{{ __('Hidden') }}</option>
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Filtering...') }}"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Categories list') }}</h4>
                <div class="text-muted small">{{ __('Showing :count category record(s) on this page.', ['count' => $categories->count()]) }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if($filters['search'] || $filters['visibility'])
                    <span class="admin-chip">{{ __('Filtered results') }}</span>
                @endif
                <a href="{{ route('admin.products.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-package-variant"></i><span>{{ __('Products') }}</span></a>
            </div>
        </div>

        @if ($categories->count() > 0)
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('name') }}">{{ __('Category') }} @if($sort === 'name') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('slug') }}">{{ __('Slug') }} @if($sort === 'slug') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('products_count') }}">{{ __('Products') }} @if($sort === 'products_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('status') }}">{{ __('Visibility') }} @if($sort === 'status') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('updated_at') }}">{{ __('Updated') }} @if($sort === 'updated_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td class="text-muted fw-semibold">#{{ $category->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if ($category->image_url)
                                            <img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="admin-thumb">
                                        @else
                                            <div class="admin-thumb d-inline-flex align-items-center justify-content-center text-muted"><i class="mdi mdi-image-outline"></i></div>
                                        @endif
                                        <div>
                                            <div class="fw-bold text-dark">{{ $category->name }}</div>
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($category->description ?: __('No description yet.'), 70) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="admin-coupon-code">{{ $category->slug ?: '—' }}</span></td>
                                <td>
                                    <div class="fw-bold">{{ $category->products_count }}</div>
                                    <div class="text-muted small">{{ __('Linked products') }}</div>
                                </td>
                                <td>
                                    @if ((int) $category->status === 0)
                                        <span class="badge admin-status-badge badge-soft-success">{{ __('Visible') }}</span>
                                    @else
                                        <span class="badge admin-status-badge badge-soft-secondary">{{ __('Hidden') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ optional($category->updated_at)->format('d M Y') }}</div>
                                    <small class="text-muted">{{ optional($category->updated_at)->format('h:i A') }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn-table-icon btn-edit" title="{{ __('Edit category') }}">
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </a>
                                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline-block" data-confirm-message="{{ __('Are you sure you want to delete this category?') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-table-icon btn-delete" title="{{ __('Delete category') }}" @disabled($category->products_count > 0)>
                                                <i class="mdi mdi-trash-can-outline"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($categories->hasPages())
                <div class="mt-4 d-flex justify-content-center">
                    {{ $categories->links() }}
                </div>
            @endif
        @else
            <div class="admin-empty-state">
                <div class="admin-empty-icon"><i class="mdi mdi-shape-outline"></i></div>
                <h5 class="mb-2 fw-bold">{{ __('No categories found') }}</h5>
                <p class="text-muted mb-3">{{ __('Try adjusting the filters or add your first category.') }}</p>
                <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">{{ __('Create category') }}</a>
            </div>
        @endif
    </div>
</div>
@endsection
