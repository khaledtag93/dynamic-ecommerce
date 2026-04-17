@extends('layouts.admin')

@section('title', __('Promotions') . ' | Admin')

@php
    $sort = $filters['sort'] ?? 'priority';
    $direction = $filters['direction'] ?? 'desc';
@endphp

@section('content')
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Sales') }}</div>
        <h1 class="admin-page-title">{{ __('Promotions') }}</h1>
        <p class="admin-page-description">{{ __('Automatic discount rules for orders, categories, and buy X get Y.') }}</p>
    </div>
    <div class="admin-page-actions">
        <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add promotion') }}</span></a>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total promotions'), 'value' => $stats['total'], 'copy' => __('All promotion rules in the system.'), 'icon' => 'mdi-sale'],
        ['label' => __('Active'), 'value' => $stats['active'], 'copy' => __('Promotion rules currently applied.'), 'icon' => 'mdi-check-circle-outline'],
        ['label' => __('Inactive'), 'value' => $stats['inactive'], 'copy' => __('Rules saved but not active right now.'), 'icon' => 'mdi-pause-circle-outline'],
        ['label' => __('Buy X Get Y'), 'value' => $stats['buy_x_get_y'], 'copy' => __('Gift and bundle style promotions.'), 'icon' => 'mdi-gift-outline'],
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
        <form method="GET" class="admin-filter-grid" data-submit-loading>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Promotion name') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Type') }}</label>
                <select name="type" class="form-select">
                    <option value="">{{ __('All types') }}</option>
                    @foreach([
                        'order_percentage' => __('Order percentage'),
                        'order_fixed' => __('Order fixed'),
                        'category_percentage' => __('Category percentage'),
                        'buy_x_get_y' => __('Buy X Get Y'),
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="admin-filter-actions admin-filter-actions-wide">
                <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Filtering...') }}"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.promotions.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Promotions list') }}</h4>
                <div class="text-muted small">{{ __('Showing :count promotion rule(s) on this page.', ['count' => $promotions->count()]) }}</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Discount') }}</th>
                        <th>{{ __('Priority') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($promotions as $promotion)
                    <tr>
                        <td class="fw-semibold">{{ $promotion->name }}</td>
                        <td>{{ __(\Illuminate\Support\Str::headline(str_replace('_', ' ', $promotion->type))) }}</td>
                        <td>{{ $promotion->category?->name ?: __('All') }}</td>
                        <td>{{ number_format((float) $promotion->discount_value, 2) }}</td>
                        <td>{{ $promotion->priority }}</td>
                        <td><span class="badge admin-status-badge {{ $promotion->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $promotion->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                <a href="{{ route('admin.promotions.edit', $promotion) }}" class="btn-table-icon btn-edit" title="{{ __('Edit promotion') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                <form method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" data-submit-loading data-confirm-message="{{ __('Delete this promotion rule?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-table-icon btn-delete" title="{{ __('Delete promotion') }}" data-loading-text="{{ __('Deleting...') }}"><i class="mdi mdi-trash-can-outline"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No promotion rules yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="mt-4">{{ $promotions->links() }}</div>
@endsection
