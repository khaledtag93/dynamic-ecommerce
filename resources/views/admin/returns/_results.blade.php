<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Return requests') }}</h4>
                <div class="text-muted small">{{ __('Showing :count return request(s) on this page.', ['count' => $returns->count()]) }}</div>
            </div>
            <span class="text-muted small">{{ __(':count total matching return request(s)', ['count' => $returns->total()]) }}</span>
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Return') }}</th>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Items') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Requested') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $returnRequest)
                        <tr>
                            <td><div class="fw-bold">{{ $returnRequest->reference }}</div></td>
                            <td>{{ $returnRequest->order?->order_number ?? '—' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $returnRequest->order?->customer_name ?? $returnRequest->user?->name ?? '—' }}</div>
                                <div class="text-muted small">{{ $returnRequest->order?->customer_email ?? $returnRequest->user?->email ?? '' }}</div>
                            </td>
                            <td>{{ $returnRequest->items_count }}</td>
                            <td><span class="badge admin-status-badge {{ $returnRequest->status_badge_class }}">{{ $returnRequest->status_label }}</span></td>
                            <td>{{ optional($returnRequest->requested_at)->format('M d, Y H:i') ?: '—' }}</td>
                            <td class="text-end"><a href="{{ route('admin.returns.show', $returnRequest) }}" class="btn btn-sm btn-light border">{{ __('Review') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-5"><div class="admin-empty-state py-4"><div class="empty-icon"><i class="mdi mdi-package-variant-closed-remove"></i></div><h5>{{ __('No return requests found') }}</h5><p class="text-muted mb-0">{{ __('Try clearing the filters or wait for a new customer return request.') }}</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($returns->hasPages())
    <div class="mt-4">{{ $returns->links() }}</div>
@endif
</div>
