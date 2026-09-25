@php
    $sort = $filters['sort'] ?? 'updated_at';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'visibility' => $filters['visibility'] ?? null,
            'usage' => $filters['usage'] ?? null,
            'readiness' => $filters['readiness'] ?? null,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.categories.index', $query);
    };
@endphp

<div data-live-results aria-busy="false">
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="mb-1">{{ __('Category operations') }}</h4>
                <p class="text-muted small mb-0">{{ __('Jump into cleanup queues before narrowing the list with detailed filters.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.categories.index', ['usage' => 'empty']) }}" data-live-link class="btn {{ $filters['usage'] === 'empty' ? 'btn-primary' : 'btn-light border' }} btn-sm btn-text-icon">
                    <i class="mdi mdi-package-variant-closed"></i><span>{{ __('Empty') }} · {{ $queueStats['empty'] }}</span>
                </a>
                <a href="{{ route('admin.categories.index', ['readiness' => 'needs_content']) }}" data-live-link class="btn {{ $filters['readiness'] === 'needs_content' ? 'btn-primary' : 'btn-light border' }} btn-sm btn-text-icon">
                    <i class="mdi mdi-text-box-search-outline"></i><span>{{ __('Needs content') }} · {{ $queueStats['needs_content'] }}</span>
                </a>
                <a href="{{ route('admin.categories.index', ['visibility' => 'hidden']) }}" data-live-link class="btn {{ $filters['visibility'] === 'hidden' ? 'btn-primary' : 'btn-light border' }} btn-sm btn-text-icon">
                    <i class="mdi mdi-eye-off-outline"></i><span>{{ __('Hidden') }} · {{ $queueStats['hidden'] }}</span>
                </a>
            </div>
        </div>
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
                @if($filters['search'] || $filters['visibility'] || $filters['usage'] || $filters['readiness'])
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
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('name') }}">{{ __('Category') }} @if($sort === 'name') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('slug') }}">{{ __('Slug') }} @if($sort === 'slug') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('products_count') }}">{{ __('Products') }} @if($sort === 'products_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('status') }}">{{ __('Visibility') }} @if($sort === 'status') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('updated_at') }}">{{ __('Updated') }} @if($sort === 'updated_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th class="text-end rtl-text-start">{{ __('Actions') }}</th>
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
                                <td><div class="fw-bold">{{ $category->products_count }}</div><div class="text-muted small">{{ __('Linked products') }}</div></td>
                                <td>
                                    @if ((int) $category->status === 0)
                                        <span class="badge admin-status-badge badge-soft-success">{{ __('Visible') }}</span>
                                    @else
                                        <span class="badge admin-status-badge badge-soft-secondary">{{ __('Hidden') }}</span>
                                    @endif
                                </td>
                                <td><div class="fw-semibold">{{ optional($category->updated_at)->format('d M Y') }}</div><small class="text-muted">{{ optional($category->updated_at)->format('h:i A') }}</small></td>
                                <td class="text-end rtl-text-start">
                                    <div class="d-flex justify-content-end rtl-justify-start gap-2 flex-wrap">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn-table-icon btn-edit" title="{{ __('Edit category') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline-block" data-submit-loading data-confirm-message="{{ __('Are you sure you want to delete this category?') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-table-icon btn-delete" title="{{ __('Delete category') }}" @disabled($category->products_count > 0)><i class="mdi mdi-trash-can-outline"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($categories->hasPages())<div class="mt-4 d-flex justify-content-center">{{ $categories->links() }}</div>@endif
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
</div>
