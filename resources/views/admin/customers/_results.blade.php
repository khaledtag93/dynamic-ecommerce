<div data-live-results aria-busy="false">
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="mb-1">{{ __('Customer operations') }}</h4>
                <p class="text-muted small mb-0">{{ __('Review acquisition, repeat purchasing, customer value, and account access from one workspace.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.customers.index', ['activity' => 'buyers']) }}" data-live-link class="btn {{ $activity === 'buyers' ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Buyers') }} · {{ $queueStats['buyers'] }}</a>
                <a href="{{ route('admin.customers.index', ['activity' => 'no_orders']) }}" data-live-link class="btn {{ $activity === 'no_orders' ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('No orders yet') }} · {{ $queueStats['no_orders'] }}</a>
                <a href="{{ route('admin.customers.index', ['value' => 'repeat']) }}" data-live-link class="btn {{ $value === 'repeat' ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Repeat buyers') }} · {{ $queueStats['repeat_buyers'] }}</a>
            </div>
        </div>
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
                                        {{ $user->roles->first()?->name ?? ((int) $user->role_as === 1 ? __('Unassigned admin (legacy)') : __('Customer')) }}
                                    </span>
                                </td>
                                <td>{{ $user->orders_count }}</td>
                                <td class="fw-semibold">EGP {{ number_format((float) ($user->orders_sum_grand_total ?? 0), 2) }}</td>
                                <td>
                                    <div>{{ $user->created_at?->format('d M Y') }}</div>
                                    <div class="text-muted small">{{ $user->created_at?->format('h:i A') }}</div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.customers.show', $user) }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-account-eye-outline"></i><span>{{ __('View account') }}</span></a>
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
