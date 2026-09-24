@extends('layouts.admin')

@section('title', __('Compensation') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header
        :kicker="__('Payroll')"
        :title="__('Compensation')"
        :description="__('Maintain the current compensation profile used when the next payroll snapshot is generated. Historical payroll keeps its own immutable rate snapshot.')"
    >
        <a href="{{ route('admin.workforce.payroll.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to payroll') }}</span></a>
    </x-admin.page-header>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <form method="GET" action="{{ route('admin.workforce.payroll.compensation.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label fw-semibold">{{ __('Search') }}</label>
                    <input type="search" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('Employee, code, department, or job title') }}">
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-semibold">{{ __('Pay basis') }}</label>
                    <select name="pay_basis" class="form-select">
                        <option value="">{{ __('All pay bases') }}</option>
                        @foreach(AppModelsEmployeeCompensation::payBasisOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($basis === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1">{{ __('Apply') }}</button>
                    <a href="{{ route('admin.workforce.payroll.compensation.index') }}" class="btn btn-light border">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Employee compensation') }}</h4>
                    <div class="text-muted small">{{ __('Salary means a fixed base amount per payroll period. Hourly pay uses effective net attendance minutes.') }}</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Pay basis') }}</th><th>{{ __('Base rate') }}</th><th>{{ __('Effective') }}</th><th>{{ __('Overtime') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($employees as $employee)
                            @php($comp = $employee->compensation)
                            <tr>
                                <td><div class="fw-bold">{{ $employee->user?->name ?: __('Missing account') }}</div><div class="text-muted small">{{ $employee->employee_code }}@if($employee->department) · {{ $employee->department }}@endif</div></td>
                                <td>{{ $comp ? (AppModelsEmployeeCompensation::payBasisOptions()[$comp->pay_basis] ?? IlluminateSupportStr::headline($comp->pay_basis)) : __('Not configured') }}</td>
                                <td>@if($comp)<span class="fw-semibold">{{ number_format((float)$comp->base_rate, 2) }} {{ $comp->currency }}</span>@else—@endif</td>
                                <td>@if($comp){{ $comp->effective_from->format('d M Y') }}@if($comp->effective_to) → {{ $comp->effective_to->format('d M Y') }}@endif @else—@endif</td>
                                <td>@if($comp && $comp->overtime_eligible)<span class="badge admin-status-badge badge-soft-success">{{ __('Eligible') }}</span>@else<span class="text-muted">{{ __('Not eligible') }}</span>@endif</td>
                                <td class="text-end">
                                    @if(auth()->user()?->hasPermission('workforce.payroll.manage'))
                                        <a href="{{ route('admin.workforce.payroll.compensation.edit', $employee) }}" class="btn btn-sm btn-primary">{{ $comp ? __('Edit') : __('Configure') }}</a>
                                    @else
                                        <span class="text-muted small">{{ __('View only') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No employees found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if($employees->hasPages())<div class="mt-4">{{ $employees->links() }}</div>@endif
</div>
@endsection
