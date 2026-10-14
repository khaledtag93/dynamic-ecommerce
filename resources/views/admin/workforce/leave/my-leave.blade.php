@extends('layouts.admin')

@section('title', __('My leave') . ' | ' . __('Admin'))

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('My leave')" :description="__('Review your leave balances, submit requests, and follow manager decisions.')">
        <a href="{{ route('admin.workforce.my-schedule') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-account-outline"></i><span>{{ __('My schedule') }}</span></a>
        <a href="{{ route('admin.workforce.time-clock') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-clock-check-outline"></i><span>{{ __('My time clock') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.view'))
            <a href="{{ route('admin.workforce.leave.index') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-calendar-check-outline"></i><span>{{ __('Leave review') }}</span></a>
        @endif
    </x-admin.page-header>

    @if(!$employee)
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="admin-empty-state py-5">
                    <div class="empty-icon"><i class="mdi mdi-account-alert-outline"></i></div>
                    <h4 class="mb-2">{{ __('Employee profile not configured') }}</h4>
                    <p class="text-muted mb-0">{{ __('Your account must be linked to an employee profile before leave can be requested.') }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Leave balances for :year', ['year' => $year]) }}</h4>
                <div class="text-muted small">{{ __('Available balance excludes approved leave; projected balance also subtracts pending requests.') }}</div>
            </div>
            <form method="GET" action="{{ route('admin.workforce.my-leave') }}" class="d-flex gap-2">
                <label class="visually-hidden" for="leaveYear">{{ __('Year') }}</label><input id="leaveYear" type="number" name="year" class="form-control" min="2000" max="2100" value="{{ $year }}" style="max-width: 120px">
                <button class="btn btn-light border">{{ __('View year') }}</button>
            </form>
        </div>

        <div class="row g-3 mb-4">
            @forelse($balances as $row)
                @php($balance = $row['balance'])
                <div class="col-md-6 col-xl-4">
                    <div class="admin-card h-100">
                        <div class="admin-card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="admin-inline-label">{{ $row['type']->code }}</div>
                                    <h4 class="mb-1">{{ $row['type']->displayName() }}</h4>
                                </div>
                                <span class="badge admin-status-badge {{ $row['type']->is_paid ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $row['type']->is_paid ? __('Paid') : __('Unpaid') }}</span>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-6"><div class="text-muted small">{{ __('Available') }}</div><div class="h4 mb-0">{{ number_format($balance['available'], 2) }}</div></div>
                                <div class="col-6"><div class="text-muted small">{{ __('Projected') }}</div><div class="h4 mb-0">{{ number_format($balance['projected_available'], 2) }}</div></div>
                                <div class="col-4"><div class="text-muted small">{{ __('Entitlement') }}</div><div class="fw-semibold">{{ number_format($balance['entitlement'], 2) }}</div></div>
                                <div class="col-4"><div class="text-muted small">{{ __('Used') }}</div><div class="fw-semibold">{{ number_format($balance['used'], 2) }}</div></div>
                                <div class="col-4"><div class="text-muted small">{{ __('Pending') }}</div><div class="fw-semibold">{{ number_format($balance['pending'], 2) }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="admin-card"><div class="admin-card-body"><div class="admin-empty-state py-4">
                        <div class="empty-icon"><i class="mdi mdi-calendar-question-outline"></i></div>
                        <h5>{{ __('No active leave types') }}</h5>
                        <p class="text-muted mb-0">{{ __('A manager must configure leave types before requests can be submitted.') }}</p>
                    </div></div></div>
                </div>
            @endforelse
        </div>

        <div class="row g-4">
            <div class="col-xl-5">
                <div class="admin-card">
                    <div class="admin-card-body">
                        <h4 class="mb-1">{{ __('Request leave') }}</h4>
                        <p class="text-muted small">{{ __('Legacy leave types count inclusive calendar days. Fixed-duration types compute the end date on the server. Configured workday types exclude only explicitly selected weekdays and holiday dates. Requests cannot cross calendar years.') }}</p>

                        @error('leave')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror

                        @if($leaveTypes->isEmpty())
                            <div class="alert alert-warning border-0 rounded-4 mb-0">{{ __('No active leave type is available for new requests.') }}</div>
                        @else
                            <form id="leaveRequestForm" method="POST" action="{{ route('admin.workforce.leave.request') }}" data-submit-loading>
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="leaveRequestType">{{ __('Leave type') }}</label>
                                    <select id="leaveRequestType" name="employee_leave_type_id" aria-required="true" class="form-select @error('employee_leave_type_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Select leave type') }}</option>
                                        @foreach($leaveTypes as $type)
                                            <option value="{{ $type->id }}" data-duration-mode="{{ $type->duration_mode ?? 'legacy' }}" @selected((string)old('employee_leave_type_id') === (string)$type->id)>{{ $type->displayName() }} · {{ $type->code }} @if($type->duration_mode === 'fixed') · {{ $type->fixed_duration_days }} {{ __('days') }} @endif</option>
                                        @endforeach
                                    </select>
                                    @error('employee_leave_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="leaveStartsOn">{{ __('Starts on') }}</label>
                                        <input id="leaveStartsOn" type="date" name="starts_on" aria-required="true" class="form-control @error('starts_on') is-invalid @enderror" value="{{ old('starts_on') }}" required>
                                        @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="leaveEndsOn">{{ __('Ends on') }}</label>
                                        <input id="leaveEndsOn" type="date" name="ends_on" aria-required="true" class="form-control @error('ends_on') is-invalid @enderror" value="{{ old('ends_on') }}" aria-describedby="leaveEndHelp">
                                        <div id="leaveEndHelp" class="form-text">{{ __('Leave end is calculated for fixed duration. Otherwise select an end date.') }}</div>
                                        @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label fw-semibold" for="leaveReason">{{ __('Reason') }}</label>
                                    <textarea id="leaveReason" name="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" maxlength="2000" placeholder="{{ __('Optional leave request context') }}">{{ old('reason') }}</textarea>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button id="leavePreviewButton" type="button" class="btn btn-outline-primary w-100 btn-text-icon mt-3"><i class="mdi mdi-calculator-variant-outline"></i><span>{{ __('Preview leave impact') }}</span></button>
                                <div id="leavePreviewResult" class="alert alert-light border mt-3 mb-0" role="status" aria-live="polite" hidden></div>
                                <p class="text-muted small mt-2 mb-0">{{ __('Preview is an estimate, not an approval or balance reservation. Submission and approval recheck the latest balance.') }}</p>
                                <button class="btn btn-primary w-100 btn-text-icon mt-3" data-loading-text="{{ __('Submitting...') }}"><i class="mdi mdi-calendar-plus"></i><span>{{ __('Submit leave request') }}</span></button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="admin-card">
                    <div class="admin-card-body">
                        <div class="admin-table-toolbar">
                            <div>
                                <h4 class="mb-1">{{ __('My leave requests') }}</h4>
                                <div class="text-muted small">{{ __('Pending requests can be cancelled until a manager reviews them.') }}</div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Days') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                                <tbody>
                                    @foreach($requests as $leaveItem)
                                        <tr>
                                            <td><div class="fw-semibold">{{ $leaveItem->leaveType?->displayName() }}</div><div class="text-muted small">{{ $leaveItem->reason ?: '—' }}</div></td>
                                            <td>{{ $leaveItem->starts_on->format('d M Y') }} → {{ $leaveItem->ends_on->format('d M Y') }}</td>
                                            <td>{{ number_format((float)$leaveItem->requested_days, 2) }}</td>
                                            <td>
                                                <span class="badge admin-status-badge {{ $leaveItem->status_badge_class }}">{{ $leaveItem->status_label }}</span>
                                                @if($leaveItem->review_notes)<div class="text-muted small mt-1">{{ $leaveItem->review_notes }}</div>@endif
                                            </td>
                                            <td class="text-end">
                                                @if($leaveItem->isPending())
                                                    <form method="POST" action="{{ route('admin.workforce.leave.cancel', $leaveItem) }}" data-confirm-message="{{ __('Cancel this pending leave request?') }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-sm btn-outline-danger">{{ __('Cancel request') }}</button>
                                                    </form>
                                                @else
                                                    <span class="text-muted small">{{ __('Review complete') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if($requests->isEmpty())
                                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No leave requests yet.') }}</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
<script>
(() => {
    const type = document.getElementById('leaveRequestType');
    const end = document.getElementById('leaveEndsOn');
    if (!type || !end) return;
    const update = () => {
        const fixed = type.selectedOptions[0]?.dataset.durationMode === 'fixed';
        end.required = !fixed;
        end.setAttribute('aria-required', String(!fixed));
        end.readOnly = fixed;
        if (fixed) end.value = '';
    };
    const form = document.getElementById('leaveRequestForm');
    const start = document.getElementById('leaveStartsOn');
    const previewButton = document.getElementById('leavePreviewButton');
    const previewResult = document.getElementById('leavePreviewResult');
    let revision = 0;

    const resetPreview = () => {
        revision++;
        if (previewResult) {
            previewResult.hidden = true;
            previewResult.textContent = '';
        }
    };
    type.addEventListener('change', () => { update(); resetPreview(); });
    start?.addEventListener('change', resetPreview);
    end.addEventListener('change', resetPreview);
    update();

    if (!form || !previewButton || !previewResult) return;
    const labels = {
        loading: @json(__('Calculating leave preview...')),
        invalid: @json(__('Unable to calculate leave preview. Please check the dates.')),
        days: @json(__('Chargeable days')),
        period: @json(__('Leave period')),
        available: @json(__('Currently available')),
        pending: @json(__('Already pending')),
        projected: @json(__('Projected before this request')),
        after: @json(__('Projected after this request')),
        warning: @json(__('Available balance is currently below the requested days. Final approval checks the balance again.')),
    };
    const number = value => Number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    previewButton.addEventListener('click', async () => {
        resetPreview();
        const current = revision;
        previewResult.hidden = false;
        previewResult.className = 'alert alert-light border mt-3 mb-0';
        previewResult.textContent = labels.loading;
        previewButton.disabled = true;
        try {
            const csrf = form.querySelector('input[name="_token"]')?.value;
            if (!csrf) throw new Error('missing-csrf');
            const params = new URLSearchParams({
                employee_leave_type_id: type.value,
                starts_on: start?.value ?? '',
                ends_on: end.value,
            });
            const response = await fetch(@json(route('admin.workforce.leave.preview')), {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-CSRF-TOKEN': csrf,
                },
                body: params,
            });
            const data = await response.json();
            if (current !== revision) return;
            if (!response.ok || !data.preview) {
                const errors = Object.values(data.errors || {}).flat();
                throw new Error(errors[0] || data.message || labels.invalid);
            }
            const estimate = data.preview;
            const lines = [
                labels.period + ': ' + estimate.starts_on + ' → ' + estimate.ends_on,
                labels.days + ': ' + number(estimate.requested_days),
                labels.available + ': ' + number(estimate.available),
                labels.pending + ': ' + number(estimate.pending),
                labels.projected + ': ' + number(estimate.projected_available),
                labels.after + ': ' + number(estimate.projected_after_request),
            ];
            if (!estimate.sufficient_balance_now) lines.push(labels.warning);
            previewResult.textContent = lines.join('\n');
            previewResult.style.whiteSpace = 'pre-line';
            previewResult.className = 'alert alert-info border mt-3 mb-0';
        } catch (error) {
            if (current !== revision) return;
            previewResult.textContent = error instanceof Error ? error.message : labels.invalid;
            previewResult.className = 'alert alert-warning border mt-3 mb-0';
        } finally {
            previewButton.disabled = false;
        }
    });
})();
</script>
@endsection
