<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Supplier settlement') }}</h4>
                <div class="text-muted small">
                    {{ __('Operational payable tracks received item cost only. Supplier invoices, shipping and tax matching, and general-ledger posting remain outside this boundary.') }}
                </div>
            </div>
        </div>

        @php($settlementLabels = [
            'not_due' => __('Not due'),
            'unpaid' => __('Unpaid'),
            'partially_paid' => __('Partially paid'),
            'paid' => __('Paid'),
        ])

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="admin-section-card h-100">
                    <div class="admin-inline-label">{{ __('Received value') }}</div>
                    <div class="fw-semibold">{{ $purchase->currency }} {{ number_format((float) $settlementSummary['payable'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-section-card h-100">
                    <div class="admin-inline-label">{{ __('Supplier paid') }}</div>
                    <div class="fw-semibold">{{ $purchase->currency }} {{ number_format((float) $settlementSummary['paid'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-section-card h-100">
                    <div class="admin-inline-label">{{ __('Balance due') }}</div>
                    <div class="fw-semibold">{{ $purchase->currency }} {{ number_format((float) $settlementSummary['balance'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-section-card h-100">
                    <div class="admin-inline-label">{{ __('Settlement status') }}</div>
                    <div class="fw-semibold">{{ $settlementLabels[$settlementSummary['status']] ?? $settlementSummary['status'] }}</div>
                </div>
            </div>
        </div>

        @if((float) $settlementSummary['balance'] > 0)
            <form method="POST" action="{{ route('admin.purchases.settlements.store', $purchase) }}" class="row g-3 align-items-end" data-submit-loading>
                @csrf
                <input type="hidden" name="settlement_key" value="{{ $settlementKey }}">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('Amount') }}</label>
                    <input type="number" step="0.01" min="0.01" max="{{ $settlementSummary['balance'] }}" name="amount" value="{{ old('amount') }}" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('Payment method') }}</label>
                    <select name="payment_method" class="form-select" required>
                        @foreach(\App\Models\PurchaseSettlement::paymentMethodOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('Reference') }}</label>
                    <input type="text" maxlength="100" name="reference" value="{{ old('reference') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" data-loading-text="{{ __('Recording...') }}">{{ __('Record supplier payment') }}</button>
                </div>
                @error('amount')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                @error('settlement_key')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
            </form>
        @endif
        @if($purchase->settlements->isNotEmpty())
            <div class="table-responsive mt-4">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($purchase->settlements as $settlement)
                        <tr>
                            <td>{{ $settlement->paid_at?->format('d M Y, H:i') }}</td>
                            <td>{{ $settlement->currency }} {{ number_format((float) $settlement->amount, 2) }}</td>
                            <td>{{ \App\Models\PurchaseSettlement::paymentMethodOptions()[$settlement->payment_method] ?? $settlement->payment_method }}</td>
                            <td>{{ $settlement->reference ?: '—' }}</td>
                            <td>{{ $settlement->status === \App\Models\PurchaseSettlement::STATUS_VOIDED ? __('Voided') : __('Active') }}</td>
                            <td>
                                @if($settlement->status === \App\Models\PurchaseSettlement::STATUS_ACTIVE)
                                    <form method="POST" action="{{ route('admin.purchases.settlements.void', [$purchase, $settlement]) }}" data-submit-loading class="d-flex gap-2">
                                        @csrf
                                        <input type="text" maxlength="1000" name="void_reason" class="form-control form-control-sm" placeholder="{{ __('Void reason') }}" required>
                                        <button class="btn btn-outline-danger btn-sm">{{ __('Void') }}</button>
                                    </form>
                                @else
                                    <span class="text-muted small">{{ $settlement->void_reason }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
