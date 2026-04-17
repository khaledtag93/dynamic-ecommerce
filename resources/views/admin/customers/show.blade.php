@extends('layouts.admin')

@section('title', __('Customer details') . ' | ' . __('Admin Dashboard'))

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Customer profile')" :title="$user->name" :description="__('Review account access, lifetime order value, and recent order activity from one customer profile.')">
        <a href="{{ route('admin.customers.index') }}" class="btn btn-light border">{{ __('Back to customers') }}</a>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Role'), 'value' => (int) $user->role_as === 1 ? __('Admin') : __('Customer'), 'copy' => $user->email, 'icon' => 'mdi-account-circle-outline'],
            ['label' => __('Orders'), 'value' => $summary['orders_count'], 'copy' => __('Total orders placed by this account.'), 'icon' => 'mdi-cart-outline'],
            ['label' => __('Total spend'), 'value' => 'EGP ' . number_format($summary['total_spend'], 2), 'copy' => __('Gross order value across all orders.'), 'icon' => 'mdi-cash-multiple'],
            ['label' => __('Refunded'), 'value' => 'EGP ' . number_format($summary['refund_total'], 2), 'copy' => __('Total refunded amount for this customer.'), 'icon' => 'mdi-cash-refund'],
        ] as $card)
            <div class="col-md-6 col-xl-3">
                <div class="admin-card admin-stat-card h-100">
                    <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                    <div class="admin-stat-label">{{ $card['label'] }}</div>
                    <div class="admin-stat-value admin-stat-value-sm">{{ $card['value'] }}</div>
                    <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="admin-card admin-card-sticky">
                <div class="admin-card-body">
                    <div class="admin-inline-label">{{ __('Account summary') }}</div>
                    <div class="mb-3">
                        <div class="fw-bold">{{ $user->name }}</div>
                        <div class="text-muted">{{ $user->email }}</div>
                        <div class="text-muted small mt-1">{{ __('Joined') }} {{ $user->created_at?->format('d M Y, h:i A') }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.customers.update-role', $user) }}" class="d-grid gap-3" data-submit-loading>
                        @csrf
                        @method('PATCH')
                        <div>
                            <label class="form-label fw-semibold">{{ __('Access role') }}</label>
                            <select name="role_as" class="form-select">
                                <option value="0" @selected((int) $user->role_as === 0)>{{ __('Customer') }}</option>
                                <option value="1" @selected((int) $user->role_as === 1)>{{ __('Admin') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Saving...') }}">{{ __('Update role') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="admin-card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <h4 class="mb-1">{{ __('Recent orders') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Latest orders linked to this account.') }}</p>
                    </div>
                    <a href="{{ route('admin.orders.index', ['search' => $user->email]) }}" class="btn btn-light border btn-sm">{{ __('Open in orders') }}</a>
                </div>
                <div class="admin-card-body pt-0">
                    @if($user->orders->count())
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Order') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Total') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th class="text-end">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($user->orders as $order)
                                        <tr>
                                            <td>
                                                <div class="fw-bold">{{ $order->order_number }}</div>
                                                <div class="text-muted small">{{ $order->payment_method_label }}</div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <span class="badge admin-status-badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                                                    <span class="badge admin-status-badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span>
                                                </div>
                                            </td>
                                            <td class="fw-semibold">EGP {{ number_format($order->grand_total, 2) }}</td>
                                            <td>
                                                <div>{{ $order->created_at?->format('d M Y') }}</div>
                                                <div class="text-muted small">{{ $order->created_at?->format('h:i A') }}</div>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-light border btn-sm">{{ __('View order') }}</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="admin-empty-state py-4">
                            <div class="admin-empty-icon"><i class="mdi mdi-cart-off"></i></div>
                            <h5 class="fw-bold mb-2">{{ __('No orders yet') }}</h5>
                            <p class="text-muted mb-0">{{ __('This account has not placed an order yet.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
