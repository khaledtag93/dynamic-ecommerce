@extends('layouts.admin')

@section('title', __('Customer details') . ' | ' . __('Admin Dashboard'))

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Customer profile')" :title="$user->name" :description="__('Review account access, lifetime order value, and recent order activity from one customer profile.')">
        <a href="{{ route('admin.customers.statement', $user) }}" class="btn btn-outline-primary btn-text-icon"><i class="mdi mdi-file-chart-outline"></i><span>{{ __('Account statement') }}</span></a>
        <a href="{{ route('admin.customers.index') }}" class="btn btn-light border">{{ __('Back to customers') }}</a>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Role'), 'value' => $user->roles->first()?->name ?? ((int) $user->role_as === 1 ? __('Unassigned admin (legacy)') : __('Customer')), 'copy' => $user->email, 'icon' => 'mdi-account-circle-outline'],
            ['label' => __('Orders'), 'value' => $summary['orders_count'], 'copy' => __('Total orders placed by this account.'), 'icon' => 'mdi-cart-outline'],
            ['label' => __('Total spend'), 'value' => 'EGP ' . number_format($summary['total_spend'], 2), 'copy' => __('Gross order value across all orders.'), 'icon' => 'mdi-cash-multiple'],
            ['label' => __('Refunded'), 'value' => 'EGP ' . number_format($summary['refund_total'], 2), 'copy' => __('Total refunded amount for this customer.'), 'icon' => 'mdi-cash-refund'],
            ['label' => __('Net spend'), 'value' => 'EGP ' . number_format($summary['net_spend'], 2), 'copy' => __('Order value after recorded refunds.'), 'icon' => 'mdi-wallet-outline'],
        ] as $card)
            <div class="col-md-6 col-xl">
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
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><div class="admin-inline-label">{{ __('Account summary') }}</div><div class="text-muted small">{{ __('Identity, access, and customer relationship context.') }}</div></div><span class="badge admin-status-badge {{ $summary['orders_count'] > 0 ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $summary['orders_count'] > 0 ? __('Buyer') : __('Registered') }}</span></div>
                    <div class="mb-3">
                        <div class="fw-bold">{{ $user->name }}</div>
                        <div class="text-muted">{{ $user->email }}</div>
                        <div class="text-muted small mt-1">{{ __('Joined') }} {{ $user->created_at?->format('d M Y, h:i A') }}</div>
                    </div>
                    <div class="row g-2 mb-4">
                        <div class="col-6"><div class="p-3 rounded-4 border h-100"><div class="text-muted small">{{ __('Average order') }}</div><div class="fw-bold">EGP {{ number_format($summary['average_order_value'], 2) }}</div></div></div>
                        <div class="col-6"><div class="p-3 rounded-4 border h-100"><div class="text-muted small">{{ __('Last order') }}</div><div class="fw-bold">{{ $summary['latest_order_at']?->format('d M Y') ?? __('No orders yet') }}</div></div></div>
                    </div>
                    <div class="border-top pt-3">
                    @if(request()->user()?->isSuperAdmin() && ! $user->isSuperAdmin())
                        <form method="POST" action="{{ route('admin.customers.update-role', $user) }}" class="d-grid gap-3" data-submit-loading data-account-access-form data-current-access="{{ (int) $user->role_as }}">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="accountAccess" class="form-label fw-semibold">{{ __('Account access') }}</label>
                                <select id="accountAccess" name="role_as" class="form-select" required>
                                    <option value="0" @selected((int) old('role_as', $user->role_as) === 0)>{{ __('Customer') }}</option>
                                    <option value="1" @selected((int) old('role_as', $user->role_as) === 1)>{{ __('Admin staff') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="accountStaffRole" class="form-label fw-semibold">{{ __('Staff role (required for admin access)') }}</label>
                                <select id="accountStaffRole" name="role_id" class="form-select">
                                    <option value="">{{ __('Select a staff role') }}</option>
                                    @foreach($staffRoles as $staffRole)
                                        <option value="{{ $staffRole->id }}" @selected((int) old('role_id', $user->roles->first()?->id) === $staffRole->id)>{{ $staffRole->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('Choose a limited staff role before granting admin access. Customer access does not use a staff role.') }}</div>
                            </div>
                            <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Saving...') }}">{{ __('Update access') }}</button>
                        </form>
                    @elseif($user->isSuperAdmin())
                        <p class="text-muted small mb-0">{{ __('Owner access cannot be changed from a customer profile.') }}</p>
                    @else
                        <p class="text-muted small mb-0">{{ __('Only the owner can change staff access.') }}</p>
                    @endif
                    </div>
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
                    <a href="{{ route('admin.orders.index', ['search' => $user->email]) }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-open-in-new"></i><span>{{ __('Open in orders') }}</span></a>
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
                                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-eye-outline"></i><span>{{ __('View order') }}</span></a>
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
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-account-access-form]');
    if (!form) return;

    const access = form.querySelector('[name="role_as"]');
    let confirmed = false;

    form.addEventListener('submit', function (event) {
        if (confirmed || !access || access.value === form.dataset.currentAccess) return;

        const message = access.value === '1'
            ? @json(__('Grant admin staff access to this account? The selected staff role will control its admin permissions.'))
            : @json(__('Return this account to customer access? Any assigned staff roles will be removed.'));

        if (window.confirm(message)) {
            confirmed = true;
            return;
        }

        event.preventDefault();
    });
});
</script>
@endpush

@endsection
