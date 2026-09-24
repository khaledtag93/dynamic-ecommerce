<div data-live-results aria-busy="false">
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h4 class="mb-1">{{ __('Procurement workspace') }}</h4>
                <p class="text-muted small mb-0">{{ __('Find purchase orders quickly and focus on stock that still needs to be received.') }}</p>
            </div>
            @if($queueStats['awaiting'] > 0)
                <a href="{{ route('admin.purchases.index', ['status' => AppModelsPurchase::STATUS_ORDERED]) }}" data-live-link class="btn {{ $filters['status'] === AppModelsPurchase::STATUS_ORDERED ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Awaiting receipt') }} · {{ $queueStats['awaiting'] }}</a>
            @endif
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Purchase list') }}</h4>
                <div class="text-muted small">{{ __('Showing :count purchase record(s) on this page.', ['count' => $purchases->count()]) }}</div>
            </div>
            <div class="admin-table-toolbar-actions">
                @if($filters['search'] || $filters['status'] || $filters['supplier_id'])
                    <span class="admin-chip">{{ __('Filtered results') }}</span>
                @endif
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-truck-delivery-outline"></i><span>{{ __('Suppliers') }}</span></a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchases as $purchase)
                        <tr>
                            <td class="fw-semibold">{{ $purchase->reference }}</td>
                            <td>
                                <div class="fw-semibold">{{ $purchase->supplier?->name ?: '—' }}</div>
                                <div class="text-muted small">{{ $purchase->supplier?->company ?: __('No company assigned') }}</div>
                            </td>
                            <td><span class="badge admin-status-badge badge-soft-info">{{ AppModelsPurchase::statusOptions()[$purchase->status] ?? ucfirst($purchase->status) }}</span></td>
                            <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                            <td class="fw-bold">EGP {{ number_format($purchase->grand_total, 2) }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn-table-icon btn-edit" title="{{ __('View purchase') }}"><i class="mdi mdi-eye-outline"></i></a>
                                    @if($purchase->status === AppModelsPurchase::STATUS_ORDERED)
                                        <form method="POST" action="{{ route('admin.purchases.receive', $purchase) }}" data-submit-loading data-confirm-title="{{ __('Confirm stock receipt') }}" data-confirm-message="{{ __('Receive this purchase and add its quantities to inventory?') }}" data-confirm-subtitle="{{ __('Receiving will update stock and cost for each line once.') }}" data-confirm-ok="{{ __('Confirm receipt') }}">
                                            @csrf
                                            <button class="btn btn-sm btn-primary btn-text-icon" data-loading-text="{{ __('Receiving...') }}"><i class="mdi mdi-package-down"></i><span>{{ __('Receive stock') }}</span></button>
                                        </form>
                                    @elseif($purchase->status === AppModelsPurchase::STATUS_RECEIVED)
                                        <span class="text-success small">{{ __('Received on :date', ['date' => optional($purchase->received_date)->format('d M Y')]) }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center">
                                <div class="admin-empty-state py-4">
                                    <div class="admin-empty-icon"><i class="mdi mdi-cart-off"></i></div>
                                    <h5 class="mb-2">{{ __('No purchases found') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Try clearing the filters or create a new purchase order.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($purchases->hasPages())
    <div class="mt-4 admin-pagination-wrap">{{ $purchases->links() }}</div>
@endif
</div>
