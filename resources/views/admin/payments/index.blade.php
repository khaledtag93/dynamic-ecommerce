@extends('layouts.admin')

@section('title', __('Payments') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Finance')" :title="__('Payments')" :description="__('Payment foundation is now ready for COD, transfer, and future gateway integrations.')">
    <a href="{{ route('admin.settings.payments') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-cog-outline"></i><span>{{ __('Payment settings') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-cash-multiple"></i></span><div class="admin-stat-label">{{ __('Total records') }}</div><div class="admin-stat-value">{{ number_format($stats['total']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-progress-clock"></i></span><div class="admin-stat-label">{{ __('Pending') }}</div><div class="admin-stat-value">{{ number_format($stats['pending']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-check-decagram-outline"></i></span><div class="admin-stat-label">{{ __('Paid') }}</div><div class="admin-stat-value">{{ number_format($stats['paid']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-alert-circle-outline"></i></span><div class="admin-stat-label">{{ __('Needs attention') }}</div><div class="admin-stat-value">{{ number_format($stats['attention']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-cash-check"></i></span><div class="admin-stat-label">{{ __('Paid amount') }}</div><div class="admin-stat-value">EGP {{ number_format($stats['paid_amount'], 2) }}</div></div></div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-3"><div><h4 class="mb-1">{{ __('Payment operations') }}</h4><p class="text-muted small mb-0">{{ __('Review payment exceptions, pending records, and successful collections from one finance workspace.') }}</p></div><div class="d-flex gap-2 flex-wrap"><a href="{{ route('admin.payments.index',['queue'=>'attention']) }}" class="btn {{ $filters['queue']==='attention' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Needs attention') }} · {{ $stats['attention'] }}</a><a href="{{ route('admin.payments.index',['queue'=>'failed']) }}" class="btn {{ $filters['queue']==='failed' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Failed') }} · {{ $stats['failed'] }}</a></div></div>
        <form method="GET" class="admin-filter-grid" data-submit-loading>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="text" class="form-control" name="search" value="{{ $filters['search'] }}" placeholder="{{ __('Reference, order number, provider') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Method') }}</label>
                <select name="method" class="form-select">
                    <option value="">{{ __('All methods') }}</option>
                    @foreach($methodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="form-label fw-semibold">{{ __('Per page') }}</label><select name="per_page" class="form-select">@foreach([20,40,80] as $size)<option value="{{ $size }}" @selected((int)$filters['per_page']===$size)>{{ $size }}</option>@endforeach</select></div><input type="hidden" name="queue" value="{{ $filters['queue'] }}"><div class="admin-filter-actions admin-filter-actions-wide">
                <button type="submit" class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.payments.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
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
                <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-receipt-text-outline"></i><span>{{ __('Orders') }}</span></a>
                <a href="{{ route('admin.settings.payments') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-cog-outline"></i><span>{{ __('Settings') }}</span></a>
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
                                    <h5 class="mb-2">{{ __('No payments recorded yet') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Once customers start placing orders, payment records will appear here automatically.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 admin-pagination-wrap">{{ $payments->links() }}</div>
</div>
@endsection
