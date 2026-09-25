@extends('layouts.admin')

@section('title', __('Account statement') . ' | ' . $user->name)

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header
        :kicker="__('Customer account')"
        :title="__('Account statement')"
        :description="__('Review dated commercial movements from canonical orders, captured payments, refunds, and returns without inventing a running balance.')"
    >
        <a href="{{ route('admin.customers.statement.print', array_merge(['user' => $user], $filters)) }}" target="_blank" rel="noopener" class="btn btn-light border btn-text-icon"><i class="mdi mdi-printer-outline"></i><span>{{ __('Print') }}</span></a>
        <a href="{{ route('admin.customers.statement.export', array_merge(['user' => $user], $filters)) }}" class="btn btn-outline-primary btn-text-icon"><i class="mdi mdi-file-delimited-outline"></i><span>{{ __('Export CSV') }}</span></a>
        <a href="{{ route('admin.customers.show', $user) }}" class="btn btn-light border">{{ __('Back to customer') }}</a>
    </x-admin.page-header>

    <div class="alert alert-info border-0 rounded-4">
        <div class="fw-semibold">{{ __('This is a commercial activity statement, not an accounting ledger.') }}</div>
        <div class="small">{{ __('Dynamic does not show a running balance here because no customer debit/credit ledger exists yet. Amounts are grouped by their recorded currency and are not converted.') }}</div>
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <form method="GET" action="{{ route('admin.customers.statement', $user) }}" class="admin-filter-grid">
                <div>
                    <label class="form-label fw-semibold">{{ __('Movement type') }}</label>
                    <select name="type" class="form-select">
                        <option value="">{{ __('All movements') }}</option>
                        <option value="order" @selected($filters['type'] === 'order')>{{ __('Orders') }}</option>
                        <option value="payment" @selected($filters['type'] === 'payment')>{{ __('Payments') }}</option>
                        <option value="refund" @selected($filters['type'] === 'refund')>{{ __('Refunds') }}</option>
                        <option value="return" @selected($filters['type'] === 'return')>{{ __('Returns') }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('From date') }}</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('To date') }}</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
                </div>
                <div class="admin-filter-actions">
                    <button class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                    <a href="{{ route('admin.customers.statement', $user) }}" class="btn btn-light border">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Orders'), 'value' => $statement['counts']['orders'], 'icon' => 'mdi-cart-outline'],
            ['label' => __('Captured payments'), 'value' => $statement['counts']['payments'], 'icon' => 'mdi-credit-card-check-outline'],
            ['label' => __('Refunds'), 'value' => $statement['counts']['refunds'], 'icon' => 'mdi-cash-refund'],
            ['label' => __('Returns'), 'value' => $statement['counts']['returns'], 'icon' => 'mdi-package-variant-closed-remove'],
        ] as $card)
            <div class="col-sm-6 col-xl-3">
                <x-admin.stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" class="h-100" />
            </div>
        @endforeach
    </div>

    @if($statement['totals_by_currency']->isNotEmpty())
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-1">{{ __('Recorded totals by currency') }}</h4>
                <p class="text-muted small mb-3">{{ __('These totals describe source records only; they are not combined into a customer balance.') }}</p>
                <div class="row g-3">
                    @foreach($statement['totals_by_currency'] as $currencyTotal)
                        <div class="col-md-6 col-xl-4">
                            <div class="rounded-4 border p-3 h-100">
                                <div class="admin-inline-label">{{ $currencyTotal['currency'] }}</div>
                                <div class="d-grid gap-2 mt-2">
                                    <div class="d-flex justify-content-between gap-3"><span class="text-muted">{{ __('Order value') }}</span><strong>{{ number_format($currencyTotal['order_value'], 2) }}</strong></div>
                                    <div class="d-flex justify-content-between gap-3"><span class="text-muted">{{ __('Captured') }}</span><strong>{{ number_format($currencyTotal['payments_captured'], 2) }}</strong></div>
                                    <div class="d-flex justify-content-between gap-3"><span class="text-muted">{{ __('Refunded') }}</span><strong>{{ number_format($currencyTotal['refunds_processed'], 2) }}</strong></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="admin-card">
        <div class="table-responsive admin-table-wrap">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Details') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($statement['movements'] as $movement)
                        <tr>
                            <td><div>{{ $movement['occurred_at']->format('d M Y') }}</div><div class="text-muted small">{{ $movement['occurred_at']->format('H:i') }}</div></td>
                            <td>{{ $movement['type_label'] }}</td>
                            <td>
                                @if($movement['url'])
                                    <a href="{{ $movement['url'] }}" class="fw-semibold">{{ $movement['reference'] }}</a>
                                @else
                                    <span class="fw-semibold">{{ $movement['reference'] }}</span>
                                @endif
                            </td>
                            <td>{{ $movement['status_label'] }}</td>
                            <td class="text-muted small">{{ $movement['details'] }}</td>
                            <td class="text-end fw-semibold">
                                @if($movement['amount'] !== null)
                                    {{ $movement['currency'] }} {{ number_format($movement['amount'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No customer movements match this period and filter.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
