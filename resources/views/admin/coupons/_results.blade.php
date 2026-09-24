@php
    $sort = $filters['sort'] ?? 'id';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'type' => $filters['type'] ?? null,
            'status' => $filters['status'] ?? null,
            'usage' => $filters['usage'] ?? null,
            'per_page' => $filters['per_page'] ?? 12,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.coupons.index', $query);
    };
@endphp

<div data-live-results aria-busy="false">
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="mb-1">{{ __('Promotion operations') }}</h4>
                <p class="text-muted small mb-0">{{ __('Review coupon health, redemption activity, expiry, and usage limits from one workspace.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.coupons.index', ['status' => 'expired']) }}" data-live-link class="btn {{ $filters['status'] === 'expired' ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Expired') }} · {{ $queueStats['expired'] }}</a>
                <a href="{{ route('admin.coupons.index', ['usage' => 'limit_reached']) }}" data-live-link class="btn {{ $filters['usage'] === 'limit_reached' ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Limit reached') }} · {{ $queueStats['limit_reached'] }}</a>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Coupons list') }}</h4>
                <div class="text-muted small">{{ __('Showing') }} {{ $coupons->count() }} {{ __('coupon record(s) on this page.') }}</div>
            </div>
            @if($filters['search'] || $filters['type'] || $filters['status'] || $filters['usage'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        @if($coupons->count())
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('code') }}">{{ __('Coupon') }} @if($sort === 'code') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('type') }}">{{ __('Type') }} @if($sort === 'type') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('value') }}">{{ __('Rule') }} @if($sort === 'value') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('used_count') }}">{{ __('Usage') }} @if($sort === 'used_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('ends_at') }}">{{ __('Status') }} @if($sort === 'ends_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
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
                                        <div class="text-muted small mt-1">{{ IlluminateSupportStr::limit($coupon->notes, 80) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $coupon->type_label }}</div>
                                    <div class="text-muted small">{{ $coupon->is_active ? __('Enabled') : __('Disabled') }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        @if($coupon->type === AppModelsCoupon::TYPE_PERCENT)
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
                <h4 class="fw-bold mb-2">{{ __('No coupons found') }}</h4>
                <p class="text-muted mb-3">{{ __('Try clearing the filters or create a new coupon.') }}</p>
                <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">{{ __('Create coupon') }}</a>
            </div>
        @endif
    </div>
</div>
</div>
