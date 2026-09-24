<div class="admin-card" data-live-results aria-busy="false">
    <div class="admin-card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Cash drawer reconciliations') }}</h4>
                <div class="text-muted small">{{ __('A central operational record for manager review. Variance is counted cash minus expected cash.') }}</div>
            </div>
            <span class="admin-chip">{{ $cashShifts->total() }} {{ __('shift(s)') }}</span>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr>
                <th>{{ __('Cashier') }}</th><th>{{ __('Opened') }}</th><th>{{ __('Closed') }}</th>
                <th class="text-end">{{ __('Opening') }}</th><th class="text-end">{{ __('Expected') }}</th>
                <th class="text-end">{{ __('Counted') }}</th><th class="text-end">{{ __('Variance') }}</th>
            </tr></thead>
            <tbody>
            @forelse($cashShifts as $shift)
                @php
                    $variance = (float) ($shift->cash_variance ?? 0);
                    $varianceLabel = abs($variance) < 0.005 ? __('Balanced') : ($variance > 0 ? __('Over') : __('Short'));
                    $varianceClass = abs($variance) < 0.005 ? 'text-success' : ($variance > 0 ? 'text-primary' : 'text-danger');
                @endphp
                <tr>
                    <td><div class="fw-semibold">{{ $shift->cashier?->name ?: __('Unknown cashier') }}</div><div class="text-muted small">{{ $shift->cashier?->email }}</div></td>
                    <td>{{ optional($shift->opened_at)->format('M d, Y H:i') }}</td>
                    <td>@if($shift->closed_at){{ $shift->closed_at->format('M d, Y H:i') }}@else<span class="badge badge-soft-success">{{ __('Open') }}</span>@endif</td>
                    <td class="text-end">EGP {{ number_format((float) $shift->opening_cash, 2) }}</td>
                    <td class="text-end">@if($shift->expected_cash !== null) EGP {{ number_format((float) $shift->expected_cash, 2) }} @else — @endif</td>
                    <td class="text-end">@if($shift->closing_cash_counted !== null) EGP {{ number_format((float) $shift->closing_cash_counted, 2) }} @else — @endif</td>
                    <td class="text-end"><span class="fw-bold {{ $shift->closed_at ? $varianceClass : 'text-muted' }}">@if($shift->closed_at){{ $variance > 0 ? '+' : '' }}EGP {{ number_format($variance, 2) }} · {{ $varianceLabel }}@else—@endif</span></td>
                </tr>
                @if($shift->opening_notes || $shift->closing_notes)
                    <tr><td colspan="7" class="pt-0 border-top-0"><div class="small text-muted">
                        @if($shift->opening_notes)<strong>{{ __('Opening note') }}:</strong> {{ $shift->opening_notes }}@endif
                        @if($shift->closing_notes)<span class="ms-3"><strong>{{ __('Closing note') }}:</strong> {{ $shift->closing_notes }}</span>@endif
                    </div></td></tr>
                @endif
            @empty
                <tr><td colspan="7" class="text-center py-5"><div class="fw-semibold">{{ __('No cash shifts match these filters.') }}</div><div class="text-muted small">{{ __('Try changing the cashier or status filters.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if($cashShifts->hasPages())<div class="mt-4">{{ $cashShifts->links() }}</div>@endif
    </div>
</div>
