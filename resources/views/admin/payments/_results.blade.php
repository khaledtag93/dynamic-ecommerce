<div data-live-results aria-busy="false">
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="mb-1">{{ __('Payment operations') }}</h4>
                <p class="text-muted small mb-0">{{ __('Review payment exceptions, pending records, and successful collections from one finance workspace.') }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.payments.index',['queue'=>'attention']) }}" data-live-link class="btn {{ $filters['queue']==='attention' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Needs attention') }} · {{ $queueStats['attention'] }}</a>
                <a href="{{ route('admin.payments.index',['queue'=>'failed']) }}" data-live-link class="btn {{ $filters['queue']==='failed' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Failed') }} · {{ $queueStats['failed'] }}</a>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Payment records') }}</h4>
                <div class="text-muted small">{{ __('Showing :count payment record(s) on this page.', ['count' => $payments->count()]) }}</div>
            </div>
            <div class="admin-table-toolbar-actions">
                @if($filters['search'] || $filters['status'] || $filters['method'] || $filters['queue'])
                    <span class="admin-chip">{{ __('Filtered results') }}</span>
                @endif
                @if(auth()->user()?->hasPermission('orders.view'))
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-receipt-text-outline"></i><span>{{ __('Orders') }}</span></a>
                @endif
                @if(auth()->user()?->hasPermission('payments.settings'))
                    <a href="{{ route('admin.settings.payments') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-cog-outline"></i><span>{{ __('Settings') }}</span></a>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Provider') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>
                                @if($payment->order)
                                    <a href="{{ route('admin.orders.show', $payment->order) }}" class="fw-semibold">{{ $payment->order->order_number }}</a>
                                    <div class="text-muted small">{{ $payment->order->customer_name }}</div>
                                @else
                                    <div class="fw-semibold">{{ __('Missing order') }}</div>
                                @endif
                            </td>
                            <td>{{ $payment->method_label }}</td>
                            <td>{{ $payment->provider ?: '—' }}</td>
                            <td><span class="badge admin-status-badge {{ $payment->status_badge_class }}">{{ $payment->status_label }}</span></td>
                            <td class="small">{{ $payment->transaction_reference ?: '—' }}</td>
                            <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ optional($payment->created_at)->format('M d, Y H:i') ?: '—' }}</td>
                            <td class="text-end"><a href="{{ route('admin.payments.show', $payment) }}" class="btn btn-sm btn-light border btn-text-icon"><i class="mdi mdi-eye-outline"></i><span>{{ __('View') }}</span></a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center">
                                <div class="admin-empty-state py-4">
                                    <div class="empty-icon"><i class="mdi mdi-cash-remove"></i></div>
                                    <h5 class="mb-2">{{ __('No payments found') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Try clearing the filters or wait for new payment activity.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($payments->hasPages())
    <div class="mt-4 admin-pagination-wrap">{{ $payments->links() }}</div>
@endif
</div>
