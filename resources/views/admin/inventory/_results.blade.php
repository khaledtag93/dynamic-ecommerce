<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Inventory movements') }}</h4>
                <div class="text-muted small">{{ __('Showing :count movement record(s) on this page.', ['count' => $movements->count()]) }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if($filters['search'] || $filters['type'] || $filters['reference'])
                    <span class="admin-chip">{{ __('Filtered results') }}</span>
                @endif
                <span class="text-muted small">{{ __(':count total matching movement(s)', ['count' => $movements->total()]) }}</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Change') }}</th>
                        <th>{{ __('Balance') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr>
                            <td>{{ optional($movement->created_at)->format('M d, Y H:i') ?: '—' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $movement->product?->name ?? __('Missing product') }}</div>
                                @if($movement->variant?->sku)
                                    <div class="text-muted small">{{ $movement->variant->sku }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge admin-status-badge badge-soft-secondary">{{ __(str_replace('_', ' ', IlluminateSupportStr::headline($movement->type ?? 'unknown'))) }}</span>
                            </td>
                            <td class="{{ (int) $movement->quantity_change >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                {{ (int) $movement->quantity_change >= 0 ? '+' : '' }}{{ (int) $movement->quantity_change }}
                            </td>
                            <td>{{ $movement->balance_after ?? '—' }}</td>
                            <td>
                                @if($movement->purchase)
                                    <span class="small">{{ __('Purchase') }} #{{ $movement->purchase->id }}</span>
                                @elseif($movement->order)
                                    <span class="small">{{ __('Order') }} {{ $movement->order->order_number ?? ('#' . $movement->order->id) }}</span>
                                @else
                                    <span class="text-muted small">{{ match ($movement->meta['source'] ?? null) {
                                        'manual_adjustment' => __('Manual adjustment'),
                                        'catalog_editor' => __('Product editor'),
                                        'catalog_inline' => __('Catalog quick edit'),
                                        default => __('Manual / system'),
                                    } }}</span>
                                @endif
                            </td>
                            <td>{{ $movement->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5">
                                <div class="admin-empty-state py-4">
                                    <div class="empty-icon"><i class="mdi mdi-archive-off-outline"></i></div>
                                    <h5 class="mb-2">{{ __('No inventory movements found') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Try clearing the filters or wait for new stock activity.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($movements->hasPages())
    <div class="mt-4">{{ $movements->links() }}</div>
@endif
</div>
