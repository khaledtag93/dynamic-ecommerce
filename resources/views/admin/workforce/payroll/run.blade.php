@extends('layouts.admin')

@section('title', __('Payroll run') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    @php
        $statusClass = match($payrollRun->status) {
            AppModelsPayrollRun::STATUS_PAID => 'badge-soft-success',
            AppModelsPayrollRun::STATUS_APPROVED => 'badge-soft-info',
            default => 'badge-soft-warning',
        };
    @endphp

    <x-admin.page-header
        :kicker="__('Payroll')"
        :title="$payrollRun->period?->name ?: __('Payroll run')"
        :description="__('Review the frozen payroll snapshot, explicit pay components, and approval lifecycle.')"
    >
        <a href="{{ route('admin.workforce.payroll.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to payroll') }}</span></a>

        @if(auth()->user()?->hasPermission('workforce.payroll.manage') && $payrollRun->isDraft())
            <form method="POST" action="{{ route('admin.workforce.payroll.runs.approve', $payrollRun) }}" data-confirm-message="{{ __('Approve this payroll run? Entries and adjustments will become immutable.') }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-primary btn-text-icon"><i class="mdi mdi-check-decagram-outline"></i><span>{{ __('Approve run') }}</span></button>
            </form>
        @elseif(auth()->user()?->hasPermission('workforce.payroll.manage') && $payrollRun->isApproved())
            <form method="POST" action="{{ route('admin.workforce.payroll.runs.paid', $payrollRun) }}" data-confirm-message="{{ __('Mark this approved payroll run as Paid?') }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-success btn-text-icon"><i class="mdi mdi-cash-check"></i><span>{{ __('Mark paid') }}</span></button>
            </form>
        @endif
    </x-admin.page-header>

    @error('payroll')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror
    @error('amount')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror
    @error('type')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Status') }}</div>
                    <span class="badge admin-status-badge {{ $statusClass }}">{{ AppModelsPayrollRun::statusOptions()[$payrollRun->status] ?? IlluminateSupportStr::headline($payrollRun->status) }}</span>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Period') }}</div>
                    <div class="fw-semibold">{{ $payrollRun->period?->starts_on?->format('d M Y') }} → {{ $payrollRun->period?->ends_on?->format('d M Y') }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">{{ __('Pay date') }}</div>
                    <div class="fw-semibold">{{ $payrollRun->period?->pay_date?->format('d M Y') ?: '—' }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">{{ __('Employees') }}</div>
                    <div class="fw-semibold">{{ $payrollRun->entries->count() }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">{{ __('Finalized by') }}</div>
                    <div class="fw-semibold">{{ $payrollRun->paidBy?->name ?: $payrollRun->approvedBy?->name ?: '—' }}</div>
                </div>
            </div>

            @if($payrollRun->approved_at || $payrollRun->paid_at)
                <div class="text-muted small mt-3">
                    @if($payrollRun->approved_at){{ __('Approved at :time', ['time' => $payrollRun->approved_at->format('d M Y H:i')]) }}@endif
                    @if($payrollRun->paid_at)<span class="ms-3">{{ __('Paid at :time', ['time' => $payrollRun->paid_at->format('d M Y H:i')]) }}</span>@endif
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach($totalsByCurrency as $summary)
            <div class="col-xl-4 col-lg-6">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="d-flex justify-content-between gap-3 align-items-start">
                            <div>
                                <div class="admin-inline-label">{{ $summary['currency'] }}</div>
                                <h4 class="mb-1">{{ __('Payroll totals') }}</h4>
                            </div>
                            <span class="admin-chip">{{ trans_choice(':count employee|:count employees', $summary['employees'], ['count' => $summary['employees']]) }}</span>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-6"><div class="text-muted small">{{ __('Base pay') }}</div><div class="fw-semibold">{{ number_format($summary['base'], 2) }}</div></div>
                            <div class="col-6"><div class="text-muted small">{{ __('Overtime') }}</div><div class="fw-semibold">{{ number_format($summary['overtime'], 2) }}</div></div>
                            <div class="col-6"><div class="text-muted small">{{ __('Allowances + bonuses') }}</div><div class="fw-semibold">{{ number_format($summary['allowances'] + $summary['bonuses'], 2) }}</div></div>
                            <div class="col-6"><div class="text-muted small">{{ __('Deductions') }}</div><div class="fw-semibold">{{ number_format($summary['deductions'], 2) }}</div></div>
                            <div class="col-6"><div class="text-muted small">{{ __('Gross pay') }}</div><div class="h5 mb-0">{{ number_format($summary['gross'], 2) }}</div></div>
                            <div class="col-6"><div class="text-muted small">{{ __('Net pay') }}</div><div class="h5 mb-0">{{ number_format($summary['net'], 2) }}</div></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="alert alert-info border-0 rounded-4">
        {{ __('V1 does not automatically calculate tax, social insurance, paid-leave credits, unpaid-leave deductions, or overtime. Any such amount must be an explicit reviewed payroll component until merchant and jurisdiction policy is configured.') }}
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Payroll entries') }}</h4>
                    <div class="text-muted small">{{ __('Employee identity, compensation, attendance, and approved leave values below are frozen snapshots from generation time.') }}</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Basis') }}</th>
                            <th>{{ __('Attendance') }}</th>
                            <th>{{ __('Leave snapshot') }}</th>
                            <th>{{ __('Base') }}</th>
                            <th>{{ __('Adjustments') }}</th>
                            <th>{{ __('Net') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payrollRun->entries as $entry)
                            @php
                                $hours = intdiv((int)$entry->net_work_minutes, 60);
                                $minutes = (int)$entry->net_work_minutes % 60;
                                $adjustmentCount = $entry->adjustments->count();
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $entry->employee_name_snapshot }}</div>
                                    <div class="text-muted small">{{ $entry->employee_code_snapshot }}</div>
                                </td>
                                <td>
                                    <div>{{ AppModelsEmployeeCompensation::payBasisOptions()[$entry->pay_basis_snapshot] ?? IlluminateSupportStr::headline($entry->pay_basis_snapshot) }}</div>
                                    <div class="text-muted small">{{ number_format((float)$entry->base_rate_snapshot, 2) }} {{ $entry->currency_snapshot }}</div>
                                </td>
                                <td>
                                    <div>{{ __(':hours h :minutes m', ['hours' => $hours, 'minutes' => $minutes]) }}</div>
                                    <div class="text-muted small">{{ trans_choice(':count session|:count sessions', $entry->attendance_session_count, ['count' => $entry->attendance_session_count]) }}</div>
                                </td>
                                <td>
                                    <div>{{ __('Paid: :days d', ['days' => number_format((float)$entry->paid_leave_days, 2)]) }}</div>
                                    <div class="text-muted small">{{ __('Unpaid: :days d', ['days' => number_format((float)$entry->unpaid_leave_days, 2)]) }}</div>
                                </td>
                                <td>{{ number_format((float)$entry->base_pay, 2) }} {{ $entry->currency_snapshot }}</td>
                                <td>
                                    <div>{{ trans_choice(':count component|:count components', $adjustmentCount, ['count' => $adjustmentCount]) }}</div>
                                    <div class="text-muted small">{{ __('Credits: :amount', ['amount' => number_format((float)$entry->overtime_pay + (float)$entry->allowances_total + (float)$entry->bonuses_total, 2)]) }}</div>
                                    <div class="text-muted small">{{ __('Deductions: :amount', ['amount' => number_format((float)$entry->deductions_total, 2)]) }}</div>
                                </td>
                                <td class="fw-bold">{{ number_format((float)$entry->net_pay, 2) }} {{ $entry->currency_snapshot }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.workforce.payroll.entries.show', $entry) }}" class="btn btn-sm btn-light border">{{ __('Payslip') }}</a>
                                </td>
                            </tr>
                            @if($entry->adjustments->isNotEmpty() || ($payrollRun->isDraft() && auth()->user()?->hasPermission('workforce.payroll.manage')))
                                <tr>
                                    <td colspan="8" class="pt-0 border-top-0">
                                        <details class="border rounded-4 p-3">
                                            <summary class="fw-semibold" style="cursor:pointer">{{ __('Pay components & adjustments') }}</summary>

                                            @if($entry->adjustments->isNotEmpty())
                                                <div class="table-responsive mt-3">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Label') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Actor') }}</th><th></th></tr></thead>
                                                        <tbody>
                                                            @foreach($entry->adjustments as $adjustment)
                                                                <tr>
                                                                    <td>{{ AppModelsPayrollAdjustment::typeOptions()[$adjustment->type] ?? IlluminateSupportStr::headline($adjustment->type) }}</td>
                                                                    <td>{{ $adjustment->label }}</td>
                                                                    <td>{{ number_format((float)$adjustment->amount, 2) }} {{ $entry->currency_snapshot }}</td>
                                                                    <td>{{ $adjustment->reason }}</td>
                                                                    <td>{{ $adjustment->createdBy?->name ?: '—' }}</td>
                                                                    <td class="text-end">
                                                                        @if($payrollRun->isDraft() && auth()->user()?->hasPermission('workforce.payroll.manage'))
                                                                            <form method="POST" action="{{ route('admin.workforce.payroll.adjustments.destroy', $adjustment) }}" data-confirm-message="{{ __('Remove this payroll component?') }}">
                                                                                @csrf
                                                                                @method('DELETE')
                                                                                <button class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button>
                                                                            </form>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif

                                            @if($payrollRun->isDraft() && auth()->user()?->hasPermission('workforce.payroll.manage'))
                                                <form method="POST" action="{{ route('admin.workforce.payroll.adjustments.store', $entry) }}" class="row g-2 mt-3" data-submit-loading>
                                                    @csrf
                                                    <div class="col-lg-2">
                                                        <select name="type" class="form-select form-select-sm" required>
                                                            @foreach(AppModelsPayrollAdjustment::typeOptions() as $value => $label)
                                                                <option value="{{ $value }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-lg-2"><input type="text" name="label" class="form-control form-control-sm" maxlength="160" required placeholder="{{ __('Label') }}"></div>
                                                    <div class="col-lg-2"><input type="number" name="amount" class="form-control form-control-sm" step="0.01" min="0.01" required placeholder="{{ __('Amount') }}"></div>
                                                    <div class="col-lg-2"><input type="number" name="quantity" class="form-control form-control-sm" step="0.001" min="0.001" placeholder="{{ __('Quantity optional') }}"></div>
                                                    <div class="col-lg-2"><input type="number" name="rate" class="form-control form-control-sm" step="0.0001" min="0.0001" placeholder="{{ __('Rate optional') }}"></div>
                                                    <div class="col-lg-2"><input type="text" name="reason" class="form-control form-control-sm" maxlength="2000" required placeholder="{{ __('Reason') }}"></div>
                                                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary" data-loading-text="{{ __('Adding...') }}">{{ __('Add component') }}</button></div>
                                                </form>
                                            @endif
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
