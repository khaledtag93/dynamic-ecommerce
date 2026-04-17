@extends('layouts.admin')

@section('title', __('Coupons') . ' | ' . __('Admin Dashboard'))

@section('content')
@php
    $sort = $filters['sort'] ?? 'id';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'type' => $filters['type'] ?? null,
            'status' => $filters['status'] ?? null,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.coupons.index', $query);
    };
@endphp
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Promotions') }}</div>
        <h1 class="admin-page-title">{{ __('Coupons') }}</h1>
        <p class="admin-page-description">{{ __('Manage discount rules, monitor usage, and keep promotional offers within safe business limits.') }}</p>
    </div>
    <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">{{ __('Create coupon') }}</a>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total coupons'), 'value' => $stats['total'], 'copy' => __('Every coupon record in the store.'), 'icon' => 'mdi-ticket-percent-outline'],
        ['label' => __('Active'), 'value' => $stats['active'], 'copy' => __('Coupons currently enabled for checkout.'), 'icon' => 'mdi-check-decagram-outline'],
        ['label' => __('Expired'), 'value' => $stats['expired'], 'copy' => __('Offers whose end date has already passed.'), 'icon' => 'mdi-calendar-remove-outline'],
        ['label' => __('Used'), 'value' => $stats['used'], 'copy' => __('Coupons that have at least one redemption.'), 'icon' => 'mdi-chart-timeline-variant'],
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
        <form method="GET" class="admin-filter-grid admin-filter-grid-coupons" data-submit-loading>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Name, code, notes') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Type') }}</label>
                <select name="type" class="form-select">
                    <option value="">{{ __('All types') }}</option>
                    @foreach($typeOptions as $value => $label)
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
                    <option value="expired" @selected($filters['status'] === 'expired')>{{ __('Expired') }}</option>
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
            <div class="admin-filter-actions admin-filter-actions-wide">
                <button class="btn btn-primary w-100" type="submit" data-loading-text="{{ __('Searching...') }}">{{ __('Apply filters') }}</button>
                <a href="{{ route('admin.coupons.index') }}" class="btn btn-light border w-100">{{ __('Reset') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Coupons list') }}</h4>
                <div class="text-muted small">{{ __('Showing') }} {{ $coupons->count() }} {{ __('coupon record(s) on this page.') }}</div>
            </div>
        </div>

        @if($coupons->count())
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><a class="table-sort-btn" href="{{ $sortLink('code') }}">{{ __('Coupon') }} @if($sort === 'code') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('type') }}">{{ __('Type') }} @if($sort === 'type') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('value') }}">{{ __('Rule') }} @if($sort === 'value') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('used_count') }}">{{ __('Usage') }} @if($sort === 'used_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('ends_at') }}">{{ __('Status') }} @if($sort === 'ends_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coupons as $coupon)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $coupon->name ?: $coupon->code }}</div>
                                    <div class="text-muted small">{{ $coupon->code }}</div>
                                    @if($coupon->notes)
                                        <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($coupon->notes, 80) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $coupon->type_label }}</div>
                                    <div class="text-muted small">{{ $coupon->is_active ? __('Enabled') : __('Disabled') }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        @if($coupon->type === \App\Models\Coupon::TYPE_PERCENT)
                                            {{ number_format($coupon->value, 2) }}%
                                        @else
                                            EGP {{ number_format($coupon->value, 2) }}
                                        @endif
                                    </div>
                                    @if($coupon->min_order_amount)
                                        <div class="text-muted small">{{ __('Min. order') }} EGP {{ number_format($coupon->min_order_amount, 2) }}</div>
                                    @endif
                                    @if($coupon->max_discount_amount)
                                        <div class="text-muted small">{{ __('Max discount') }} EGP {{ number_format($coupon->max_discount_amount, 2) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / ' . $coupon->usage_limit : '' }}</div>
                                    <div class="text-muted small">{{ $coupon->starts_at ? $coupon->starts_at->format('d M Y') : __('Any time') }} — {{ $coupon->ends_at ? $coupon->ends_at->format('d M Y') : __('No end') }}</div>
                                </td>
                                <td>
                                    <span class="badge admin-status-badge {{ $coupon->isUsable() ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $coupon->isUsable() ? __('Usable') : __('Limited / inactive') }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn-table-icon btn-edit" title="{{ __('Edit Coupon') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" data-submit-loading data-confirm-message="{{ __('Delete this coupon?') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-table-icon btn-delete" title="{{ __('Delete this coupon?') }}" data-loading-text="{{ __('Deleting...') }}"><i class="mdi mdi-trash-can-outline"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($coupons->hasPages())
                <div class="mt-4 d-flex justify-content-center">{{ $coupons->links() }}</div>
            @endif
        @else
            <div class="admin-empty-state">
                <div class="admin-empty-icon"><i class="mdi mdi-ticket-percent-outline"></i></div>
                <h4 class="fw-bold mb-2">{{ __('No coupons yet') }}</h4>
                <p class="text-muted mb-3">{{ __('Create your first coupon to unlock discount logic in cart and checkout.') }}</p>
                <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">{{ __('Create coupon') }}</a>
            </div>
        @endif
    </div>
</div>
@endsection
