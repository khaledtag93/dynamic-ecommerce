@extends('layouts.admin')

@section('title', __('My payslips') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header
        :kicker="__('Workforce')"
        :title="__('My payslips')"
        :description="__('View finalized payroll statements assigned to your employee profile.')"
    >
        <a href="{{ route('admin.workforce.time-clock') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-clock-check-outline"></i><span>{{ __('My time clock') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.payroll.view'))
            <a href="{{ route('admin.workforce.payroll.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-cash-multiple"></i><span>{{ __('Payroll') }}</span></a>
        @endif
    </x-admin.page-header>

    @if(!$employee)
        <div class="admin-card"><div class="admin-card-body">
            <div class="admin-empty-state py-5">
                <div class="empty-icon"><i class="mdi mdi-account-alert-outline"></i></div>
                <h4>{{ __('Employee profile not configured') }}</h4>
                <p class="text-muted mb-0">{{ __('Your account must be linked to an employee profile before payslips can be displayed.') }}</p>
            </div>
        </div></div>
    @else
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="admin-table-toolbar">
                    <div>
                        <h4 class="mb-1">{{ __('Finalized payroll statements') }}</h4>
                        <div class="text-muted small">{{ __('Draft payroll never appears in My Payslips.') }}</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Status') }}</th><th>{{ __('Gross pay') }}</th><th>{{ __('Deductions') }}</th><th>{{ __('Net pay') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                        <tbody>
                            @forelse($entries as $entry)
                                <tr>
                                    <td><div class="fw-semibold">{{ $entry->payrollRun?->period?->name }}</div><div class="text-muted small">{{ $entry->payrollRun?->period?->starts_on?->format('d M Y') }} → {{ $entry->payrollRun?->period?->ends_on?->format('d M Y') }}</div></td>
                                    <td><span class="badge admin-status-badge {{ $entry->payrollRun?->isPaid() ? 'badge-soft-success' : 'badge-soft-info' }}">{{ AppModelsPayrollRun::statusOptions()[$entry->payrollRun->status] ?? IlluminateSupportStr::headline($entry->payrollRun->status) }}</span></td>
                                    <td>{{ number_format((float)$entry->gross_pay, 2) }} {{ $entry->currency_snapshot }}</td>
                                    <td>{{ number_format((float)$entry->deductions_total, 2) }} {{ $entry->currency_snapshot }}</td>
                                    <td class="fw-bold">{{ number_format((float)$entry->net_pay, 2) }} {{ $entry->currency_snapshot }}</td>
                                    <td class="text-end"><a href="{{ route('admin.workforce.payroll.my-payslip', $entry) }}" class="btn btn-sm btn-primary">{{ __('Open payslip') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No finalized payslips yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if($entries->hasPages())<div class="mt-4">{{ $entries->links() }}</div>@endif
    @endif
</div>
@endsection
