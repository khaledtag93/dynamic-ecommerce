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
        ['label' => __('Running now'), 'value' => $stats['running'], 'copy' => __('Active promotions currently inside their schedule.'), 'icon' => 'mdi-play-circle-outline'],
        ['label' => __('Upcoming'), 'value' => $stats['upcoming'], 'copy' => __('Scheduled promotions that have not started yet.'), 'icon' => 'mdi-calendar-clock-outline'],
        ['label' => __('Expired'), 'value' => $stats['expired'], 'copy' => __('Promotion schedules that have already ended.'), 'icon' => 'mdi-calendar-remove-outline'],
    ] as $card)
        <div class="col-md-6 col-xl">
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
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-3"><div><h4 class="mb-1">{{ __('Promotion schedule') }}</h4><p class="text-muted small mb-0">{{ __('Separate live campaigns from upcoming and expired rules before changing pricing behavior.') }}</p></div><div class="d-flex flex-wrap gap-2"><a href="{{ route('admin.promotions.index',['schedule'=>'running']) }}" class="btn {{ $filters['schedule']==='running' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Running now') }} · {{ $stats['running'] }}</a><a href="{{ route('admin.promotions.index',['schedule'=>'upcoming']) }}" class="btn {{ $filters['schedule']==='upcoming' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Upcoming') }} · {{ $stats['upcoming'] }}</a><a href="{{ route('admin.promotions.index',['schedule'=>'expired']) }}" class="btn {{ $filters['schedule']==='expired' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Expired') }} · {{ $stats['expired'] }}</a></div></div>
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
            <div><label class="form-label fw-semibold">{{ __('Schedule') }}</label><select name="schedule" class="form-select"><option value="">{{ __('All schedules') }}</option><option value="running" @selected($filters['schedule']==='running')>{{ __('Running now') }}</option><option value="upcoming" @selected($filters['schedule']==='upcoming')>{{ __('Upcoming') }}</option><option value="expired" @selected($filters['schedule']==='expired')>{{ __('Expired') }}</option></select></div><div><label class="form-label fw-semibold">{{ __('Per page') }}</label><select name="per_page" class="form-select">@foreach([20,40,80] as $size)<option value="{{ $size }}" @selected((int)$filters['per_page']===$size)>{{ $size }}</option>@endforeach</select></div><div class="admin-filter-actions admin-filter-actions-wide">
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
