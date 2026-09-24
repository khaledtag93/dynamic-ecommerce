@extends('layouts.admin')

@section('title', __('Payslip') . ' | Admin')

@section('content')
<div class="admin-page-shell payroll-payslip">
    <x-admin.page-header
        :kicker="__('Payroll')"
        :title="__('Payslip')"
        :description="__('Frozen payroll statement generated from the finalized payroll entry snapshot.')"
    >
        @if($selfView)
            <a href="{{ route('admin.workforce.payroll.my-payslips') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to my payslips') }}</span></a>
        @else
            <a href="{{ route('admin.workforce.payroll.runs.show', $entry->payrollRun) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to payroll run') }}</span></a>
        @endif
        <button type="button" class="btn btn-primary btn-text-icon" onclick="window.print()"><i class="mdi mdi-printer-outline"></i><span>{{ __('Print') }}</span></button>
    </x-admin.page-header>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between gap-4 flex-wrap">
                <div>
                    <div class="admin-inline-label">{{ __('Employee') }}</div>
                    <h2 class="mb-1">{{ $entry->employee_name_snapshot }}</h2>
                    <div class="text-muted">{{ $entry->employee_code_snapshot }}</div>
                </div>
                <div class="text-end">
                    <div class="admin-inline-label">{{ __('Payroll period') }}</div>
                    <h4 class="mb-1">{{ $entry->payrollRun?->period?->name }}</h4>
                    <div class="text-muted">{{ $entry->payrollRun?->period?->starts_on?->format('d M Y') }} → {{ $entry->payrollRun?->period?->ends_on?->format('d M Y') }}</div>
                    @if($entry->payrollRun?->period?->pay_date)<div class="text-muted">{{ __('Pay date: :date', ['date' => $entry->payrollRun->period->pay_date->format('d M Y')]) }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    @php
        $hours = intdiv((int)$entry->net_work_minutes, 60);
        $minutes = (int)$entry->net_work_minutes % 60;
    @endphp

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="admin-card h-100"><div class="admin-card-body">
                <h4 class="mb-3">{{ __('Snapshot inputs') }}</h4>
                <div class="mb-3"><div class="text-muted small">{{ __('Pay basis') }}</div><div class="fw-semibold">{{ AppModelsEmployeeCompensation::payBasisOptions()[$entry->pay_basis_snapshot] ?? IlluminateSupportStr::headline($entry->pay_basis_snapshot) }}</div></div>
                <div class="mb-3"><div class="text-muted small">{{ __('Base rate') }}</div><div class="fw-semibold">{{ number_format((float)$entry->base_rate_snapshot, 2) }} {{ $entry->currency_snapshot }}</div></div>
                <div class="mb-3"><div class="text-muted small">{{ __('Effective net attendance') }}</div><div class="fw-semibold">{{ __(':hours h :minutes m', ['hours' => $hours, 'minutes' => $minutes]) }}</div></div>
                <div class="mb-3"><div class="text-muted small">{{ __('Attendance sessions') }}</div><div class="fw-semibold">{{ $entry->attendance_session_count }}</div></div>
                <div class="mb-3"><div class="text-muted small">{{ __('Paid leave snapshot') }}</div><div class="fw-semibold">{{ number_format((float)$entry->paid_leave_days, 2) }} {{ __('days') }}</div></div>
                <div><div class="text-muted small">{{ __('Unpaid leave snapshot') }}</div><div class="fw-semibold">{{ number_format((float)$entry->unpaid_leave_days, 2) }} {{ __('days') }}</div></div>
            </div></div>
        </div>

        <div class="col-xl-8">
            <div class="admin-card h-100"><div class="admin-card-body">
                <h4 class="mb-3">{{ __('Pay statement') }}</h4>
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <tbody>
                            <tr><td>{{ __('Base pay') }}</td><td class="text-end fw-semibold">{{ number_format((float)$entry->base_pay, 2) }} {{ $entry->currency_snapshot }}</td></tr>
                            @foreach($entry->adjustments->where('type', AppModelsPayrollAdjustment::TYPE_OVERTIME) as $adjustment)
                                <tr><td>{{ __('Overtime') }} · {{ $adjustment->label }}<div class="text-muted small">{{ $adjustment->reason }}</div></td><td class="text-end">{{ number_format((float)$adjustment->amount, 2) }} {{ $entry->currency_snapshot }}</td></tr>
                            @endforeach
                            @foreach($entry->adjustments->where('type', AppModelsPayrollAdjustment::TYPE_ALLOWANCE) as $adjustment)
                                <tr><td>{{ __('Allowance') }} · {{ $adjustment->label }}<div class="text-muted small">{{ $adjustment->reason }}</div></td><td class="text-end">{{ number_format((float)$adjustment->amount, 2) }} {{ $entry->currency_snapshot }}</td></tr>
                            @endforeach
                            @foreach($entry->adjustments->where('type', AppModelsPayrollAdjustment::TYPE_BONUS) as $adjustment)
                                <tr><td>{{ __('Bonus') }} · {{ $adjustment->label }}<div class="text-muted small">{{ $adjustment->reason }}</div></td><td class="text-end">{{ number_format((float)$adjustment->amount, 2) }} {{ $entry->currency_snapshot }}</td></tr>
                            @endforeach
                            <tr class="fw-bold"><td>{{ __('Gross pay') }}</td><td class="text-end">{{ number_format((float)$entry->gross_pay, 2) }} {{ $entry->currency_snapshot }}</td></tr>
                            @foreach($entry->adjustments->where('type', AppModelsPayrollAdjustment::TYPE_DEDUCTION) as $adjustment)
                                <tr><td>{{ __('Deduction') }} · {{ $adjustment->label }}<div class="text-muted small">{{ $adjustment->reason }}</div></td><td class="text-end text-danger">-{{ number_format((float)$adjustment->amount, 2) }} {{ $entry->currency_snapshot }}</td></tr>
                            @endforeach
                            <tr class="fw-bold fs-5"><td>{{ __('Net pay') }}</td><td class="text-end">{{ number_format((float)$entry->net_pay, 2) }} {{ $entry->currency_snapshot }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
    </div>

    <div class="alert alert-warning border-0 rounded-4 mt-4 mb-0">
        {{ __('This V1 payroll statement does not automatically apply statutory tax, social insurance, or jurisdiction-specific deductions. Only explicit approved payroll components are reflected.') }}
    </div>
</div>
@endsection
