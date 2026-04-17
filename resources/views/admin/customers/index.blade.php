@extends('layouts.admin')

@section('title', __('Customers') . ' | ' . __('Admin Dashboard'))

@section('content')
<x-admin.page-header :kicker="__('People & access')" :title="__('Customers')" :description="__('Review registered users, search quickly, and control who gets admin access with safer role management.')" />

<div class="admin-page-shell">
<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total users'), 'value' => $stats['total'], 'copy' => __('All registered accounts in the platform.'), 'icon' => 'mdi-account-group-outline'],
        ['label' => __('Customers'), 'value' => $stats['customers'], 'copy' => __('Standard customer accounts.'), 'icon' => 'mdi-account-outline'],
        ['label' => __('Admins'), 'value' => $stats['admins'], 'copy' => __('Users with admin dashboard access.'), 'icon' => 'mdi-shield-account-outline'],
        ['label' => __('Buyers'), 'value' => $stats['buyers'], 'copy' => __('Accounts that have at least one order.'), 'icon' => 'mdi-cart-check'],
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
        <form method="GET" class="admin-filter-grid admin-filter-grid-customers" data-submit-loading>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Customer name or email') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Role') }}</label>
                <select name="role" class="form-select">
                    <option value="">{{ __('All roles') }}</option>
                    <option value="0" @selected($role === '0')>{{ __('Customers') }}</option>
                    <option value="1" @selected($role === '1')>{{ __('Admins') }}</option>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Filtering...') }}"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.customers.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Users list') }}</h4>
                <div class="text-muted small">{{ __('Showing :count user(s) on this page.', ['count' => $users->count()]) }}</div>
            </div>
        </div>

        @if($users->count())
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Orders') }}</th>
                            <th>{{ __('Spend') }}</th>
                            <th>{{ __('Joined') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <div class="text-muted small">{{ $user->email }}</div>
                                </td>
                                <td>
                                    <span class="badge admin-status-badge {{ (int) $user->role_as === 1 ? 'badge-soft-info' : 'badge-soft-secondary' }}">
                                        {{ (int) $user->role_as === 1 ? __('Admin') : __('Customer') }}
                                    </span>
                                </td>
                                <td>{{ $user->orders_count }}</td>
                                <td class="fw-semibold">EGP {{ number_format((float) ($user->orders_sum_grand_total ?? 0), 2) }}</td>
                                <td>
                                    <div>{{ $user->created_at?->format('d M Y') }}</div>
                                    <div class="text-muted small">{{ $user->created_at?->format('h:i A') }}</div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end flex-wrap">
                                        <a href="{{ route('admin.customers.show', $user) }}" class="btn-table-icon btn-view" title="{{ __('View customer') }}"><i class="mdi mdi-eye-outline"></i></a>
                                        <form method="POST" action="{{ route('admin.customers.update-role', $user) }}" class="admin-inline-update justify-content-end" data-submit-loading>
                                            @csrf
                                            @method('PATCH')
                                            <select name="role_as" class="form-select form-select-sm admin-inline-select" style="min-width: 130px;">
                                                <option value="0" @selected((int) $user->role_as === 0)>{{ __('Customer') }}</option>
                                                <option value="1" @selected((int) $user->role_as === 1)>{{ __('Admin') }}</option>
                                            </select>
                                            <button type="submit" class="btn-table-icon btn-save" title="{{ __('Save role') }}" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-check"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="admin-empty-state">
                <div class="admin-empty-icon"><i class="mdi mdi-account-search-outline"></i></div>
                <h4 class="fw-bold mb-2">{{ __('No users found') }}</h4>
                <p class="text-muted mb-0">{{ __('Try clearing the filters or wait for new customers to register.') }}</p>
            </div>
        @endif

        @if($users->hasPages())
            <div class="mt-4 d-flex justify-content-center">{{ $users->links() }}</div>
        @endif
    </div>
</div>
</div>
@endsection
