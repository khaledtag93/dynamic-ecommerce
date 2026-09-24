@extends('layouts.admin')

@section('title', __('Payroll') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header
        :kicker="__('Workforce')"
        :title="__('Payroll')"
        :description="__('Create payroll periods, generate immutable attendance/leave snapshots, and move runs through Draft, Approved, and Paid states.')"
    >
        <a href="{{ route('admin.workforce.payroll.compensation.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-account-cash-outline"></i><span>{{ __('Compensation') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.payroll.self'))
            <a href="{{ route('admin.workforce.payroll.my-payslips') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-receipt-text-outline"></i><span>{{ __('My payslips') }}</span></a>
        @endif
    </x-admin.page-header>

    @error('payroll')
        <div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>
    @enderror

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Open periods'), 'value' => $stats['open_periods'], 'icon' => 'mdi-calendar-clock-outline'],
            ['label' => __('Draft runs'), 'value' => $stats['draft_runs'], 'icon' => 'mdi-file-document-edit-outline'],
            ['label' => __('Approved runs'), 'value' => $stats['approved_runs'], 'icon' => 'mdi-check-decagram-outline'],
            ['label' => __('Paid runs'), 'value' => $stats['paid_runs'], 'icon' => 'mdi-cash-check'],
        ] as $card)
            <div class="col-md-6 col-xl-3">
                <div class="admin-card admin-stat-card h-100">
                    <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                    <div class="admin-stat-label">{{ $card['label'] }}</div>
                    <div class="admin-stat-value">{{ $card['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if(auth()->user()?->hasPermission('workforce.payroll.manage'))
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="admin-section-heading">
                    <div>
                        <h4 class="admin-section-title">{{ __('Create payroll period') }}</h4>
                        <p class="admin-section-subtitle">{{ __('Periods cannot overlap. Pay date, when supplied, must be on or after the period end date.') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.workforce.payroll.periods.store') }}" data-submit-loading>
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">{{ __('Period name') }}</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" maxlength="120" value="{{ old('name') }}" required placeholder="{{ __('Example: October 2026') }}">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label fw-semibold">{{ __('Starts on') }}</label>
                            <input type="date" name="starts_on" class="form-control @error('starts_on') is-invalid @enderror" value="{{ old('starts_on') }}" required>
                            @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label fw-semibold">{{ __('Ends on') }}</label>
                            <input type="date" name="ends_on" class="form-control @error('ends_on') is-invalid @enderror" value="{{ old('ends_on') }}" required>
                            @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label fw-semibold">{{ __('Pay date') }}</label>
                            <input type="date" name="pay_date" class="form-control @error('pay_date') is-invalid @enderror" value="{{ old('pay_date') }}">
                            @error('pay_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" data-loading-text="{{ __('Creating...') }}">{{ __('Create period') }}</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">{{ __('Notes') }}</label>
                            <textarea name="notes" rows="2" maxlength="2000" class="form-control">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Payroll periods') }}</h4>
                    <div class="text-muted small">{{ __('Generating a run freezes its attendance, leave, compensation, and employee identity inputs into payroll entries.') }}</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Period') }}</th>
                            <th>{{ __('Dates') }}</th>
                            <th>{{ __('Pay date') }}</th>
                            <th>{{ __('Period status') }}</th>
                            <th>{{ __('Run status') }}</th>
                            <th>{{ __('Employees') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                            @php($run = $period->payrollRun)
                            <tr>
                                <td><div class="fw-bold">{{ $period->name }}</div>@if($period->notes)<div class="text-muted small">{{ $period->notes }}</div>@endif</td>
                                <td>{{ $period->starts_on->format('d M Y') }} → {{ $period->ends_on->format('d M Y') }}</td>
                                <td>{{ $period->pay_date?->format('d M Y') ?: '—' }}</td>
                                <td>
                                    <span class="badge admin-status-badge {{ $period->isOpen() ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ AppModelsPayrollPeriod::statusOptions()[$period->status] ?? IlluminateSupportStr::headline($period->status) }}</span>
                                </td>
                                <td>
                                    @if($run)
                                        @php($runClass = match($run->status) {
                                            AppModelsPayrollRun::STATUS_PAID => 'badge-soft-success',
                                            AppModelsPayrollRun::STATUS_APPROVED => 'badge-soft-info',
                                            default => 'badge-soft-warning',
                                        })
                                        <span class="badge admin-status-badge {{ $runClass }}">{{ AppModelsPayrollRun::statusOptions()[$run->status] ?? IlluminateSupportStr::headline($run->status) }}</span>
                                    @else
                                        <span class="text-muted">{{ __('Not generated') }}</span>
                                    @endif
                                </td>
                                <td>{{ $run?->entries?->count() ?? 0 }}</td>
                                <td class="text-end">
                                    @if($run)
                                        <a href="{{ route('admin.workforce.payroll.runs.show', $run) }}" class="btn btn-sm btn-light border">{{ __('Open run') }}</a>
                                    @elseif(auth()->user()?->hasPermission('workforce.payroll.manage') && $period->isOpen())
                                        <form method="POST" action="{{ route('admin.workforce.payroll.periods.generate', $period) }}" data-confirm-message="{{ __('Generate payroll snapshot for this period? Resolve attendance corrections and leave requests first.') }}">
                                            @csrf
                                            <button class="btn btn-sm btn-primary">{{ __('Generate draft') }}</button>
                                        </form>
                                    @else
                                        <span class="text-muted small">{{ __('View only') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No payroll periods yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($periods->hasPages())<div class="mt-4">{{ $periods->links() }}</div>@endif
</div>
@endsection
