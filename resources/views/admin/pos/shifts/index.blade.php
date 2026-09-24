@extends('layouts.admin')

@section('title', __('Cash Shift Review') . ' | Admin')
@section('content')
<x-admin.page-header :kicker="__('Point of Sale')" :title="__('Cash Shift Review')" :description="__('Review cashier drawer sessions, expected cash, counted cash, and reconciliation variances.')">
<a href="{{ route('admin.pos.index') }}" class="btn btn-light border"><i class="mdi mdi-cash-register me-1"></i>{{ __('Open POS') }}</a>
</x-admin.page-header>
<div class="admin-page-shell">
<div class="row g-3">
<div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Open shifts') }}</div><div class="h3 mb-0">{{ number_format($shiftMetrics['open']) }}</div></div></div></div>
<div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Closed shifts') }}</div><div class="h3 mb-0">{{ number_format($shiftMetrics['closed']) }}</div></div></div></div>
<div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Shifts with variance') }}</div><div class="h3 mb-0">{{ number_format($shiftMetrics['with_variance']) }}</div></div></div></div>
<div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Total shortage') }}</div><div class="h3 mb-0 text-danger">EGP {{ number_format($shiftMetrics['short_total'], 2) }}</div></div></div></div>
<div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Total overage') }}</div><div class="h3 mb-0 text-primary">EGP {{ number_format($shiftMetrics['over_total'], 2) }}</div></div></div></div>
</div>
<div class="admin-card mb-4"><div class="admin-card-body">
<form method="GET" action="{{ route('admin.pos.shifts.index') }}" class="row g-3 align-items-end">
<div class="col-lg-5"><label class="form-label fw-semibold">{{ __('Cashier') }}</label><input type="search" name="cashier" value="{{ $cashierSearch }}" class="form-control" placeholder="{{ __('Search cashier by name or email') }}"></div>
<div class="col-lg-4"><label class="form-label fw-semibold">{{ __('Shift status') }}</label><select name="status" class="form-select"><option value="">{{ __('All shifts') }}</option><option value="open" @selected($status === 'open')>{{ __('Open') }}</option><option value="closed" @selected($status === 'closed')>{{ __('Closed') }}</option><option value="variance" @selected($status === 'variance')>{{ __('With variance') }}</option></select></div>
<div class="col-lg-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1">{{ __('Apply filters') }}</button><a href="{{ route('admin.pos.shifts.index') }}" class="btn btn-light border">{{ __('Reset') }}</a></div>
</form></div></div>
<div class="admin-card"><div class="admin-card-body">
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3"><div><h4 class="mb-1">{{ __('Cash drawer reconciliations') }}</h4><div class="text-muted small">{{ __('A central operational record for manager review. Variance is counted cash minus expected cash.') }}</div></div><span class="admin-chip">{{ $cashShifts->total() }} {{ __('shift(s)') }}</span></div>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ __('Cashier') }}</th><th>{{ __('Opened') }}</th><th>{{ __('Closed') }}</th><th class="text-end">{{ __('Opening') }}</th><th class="text-end">{{ __('Expected') }}</th><th class="text-end">{{ __('Counted') }}</th><th class="text-end">{{ __('Variance') }}</th></tr></thead><tbody>
@forelse($cashShifts as $shift)
@php($variance=(float)($shift->cash_variance??0))
@php($varianceLabel=abs($variance)<0.005?__('Balanced'):($variance>0?__('Over'):__('Short')))
@php($varianceClass=abs($variance)<0.005?'text-success':($variance>0?'text-primary':'text-danger'))
<tr><td><div class="fw-semibold">{{ $shift->cashier?->name ?: __('Unknown cashier') }}</div><div class="text-muted small">{{ $shift->cashier?->email }}</div></td><td>{{ optional($shift->opened_at)->format('M d, Y H:i') }}</td><td>@if($shift->closed_at){{ $shift->closed_at->format('M d, Y H:i') }}@else<span class="badge badge-soft-success">{{ __('Open') }}</span>@endif</td><td class="text-end">EGP {{ number_format((float)$shift->opening_cash,2) }}</td><td class="text-end">@if($shift->expected_cash!==null) EGP {{ number_format((float)$shift->expected_cash,2) }} @else — @endif</td><td class="text-end">@if($shift->closing_cash_counted!==null) EGP {{ number_format((float)$shift->closing_cash_counted,2) }} @else — @endif</td><td class="text-end"><span class="fw-bold {{ $shift->closed_at?$varianceClass:'text-muted' }}">@if($shift->closed_at){{ $variance>0?'+':'' }}EGP {{ number_format($variance,2) }} · {{ $varianceLabel }}@else—@endif</span></td></tr>
@if($shift->opening_notes||$shift->closing_notes)<tr><td colspan="7" class="pt-0 border-top-0"><div class="small text-muted">@if($shift->opening_notes)<strong>{{ __('Opening note') }}:</strong> {{ $shift->opening_notes }}@endif @if($shift->closing_notes)<span class="ms-3"><strong>{{ __('Closing note') }}:</strong> {{ $shift->closing_notes }}</span>@endif</div></td></tr>@endif
@empty
<tr><td colspan="7" class="text-center py-5"><div class="fw-semibold">{{ __('No cash shifts match these filters.') }}</div><div class="text-muted small">{{ __('Try changing the cashier or status filters.') }}</div></td></tr>
@endforelse
</tbody></table></div>
@if($cashShifts->hasPages())<div class="mt-4">{{ $cashShifts->links() }}</div>@endif
</div></div></div>
@endsection
