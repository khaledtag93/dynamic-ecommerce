@extends('layouts.admin')

@section('title', __('My time clock') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('My time clock')" :description="__('Clock attendance, record breaks, and request audited corrections without changing the original history.')">
        <a href="{{ route('admin.workforce.my-schedule') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-account-outline"></i><span>{{ __('My schedule') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.view'))
            <a href="{{ route('admin.workforce.attendance.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-clock-outline"></i><span>{{ __('Attendance review') }}</span></a>
            <a href="{{ route('admin.workforce.corrections.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-file-document-edit-outline"></i><span>{{ __('Correction requests') }}</span></a>
        @endif
    </x-admin.page-header>

    @if(!$employee)
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="admin-empty-state py-5">
                    <div class="empty-icon"><i class="mdi mdi-account-alert-outline"></i></div>
                    <h4 class="mb-2">{{ __('Employee profile not configured') }}</h4>
                    <p class="text-muted mb-0">{{ __('Your account can use the time clock, but it must be linked to an employee profile first.') }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-xl-5">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                            <div>
                                <div class="admin-inline-label">{{ __('Employee') }}</div>
                                <h3 class="mb-1">{{ $employee->user?->name }}</h3>
                                <div class="text-muted">{{ $employee->employee_code }} · {{ $employee->job_title ?: __('No job title') }}</div>
                            </div>
                            @if($employee->openAttendanceSession?->openBreak)
                                <span class="badge admin-status-badge badge-soft-warning">{{ __('On break') }}</span>
                            @else
                                <span class="badge admin-status-badge {{ $employee->openAttendanceSession ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $employee->openAttendanceSession ? __('Clocked in') : __('Clocked out') }}</span>
                            @endif
                        </div>

                        <div class="admin-card mb-4">
                            <div class="admin-card-body">
                                @if($employee->openAttendanceSession)
                                    <div class="admin-stat-label">{{ __('Current session started') }}</div>
                                    <div class="admin-stat-value fs-3">{{ $employee->openAttendanceSession->clock_in_at->format('H:i') }}</div>
                                    <div class="text-muted small">{{ $employee->openAttendanceSession->clock_in_at->format('d M Y') }}</div>

                                    @if($employee->openAttendanceSession->openBreak)
                                        <div class="mt-3 p-3 rounded border">
                                            <div class="fw-semibold">{{ __('Break in progress') }}</div>
                                            <div class="text-muted small">{{ __('Started at :time', ['time' => $employee->openAttendanceSession->openBreak->starts_at->format('H:i')]) }}</div>
                                        </div>
                                    @endif
                                @else
                                    <div class="admin-stat-label">{{ __('Current status') }}</div>
                                    <div class="admin-stat-value fs-3">{{ __('Ready to clock in') }}</div>
                                    <div class="text-muted small">{{ __('Your next attendance session starts when you confirm clock in.') }}</div>
                                @endif
                            </div>
                        </div>

                        @error('attendance')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror

                        @if($employee->openAttendanceSession)
                            @if($employee->openAttendanceSession->openBreak)
                                <form method="POST" action="{{ route('admin.workforce.break-end') }}" data-submit-loading class="mb-3">
                                    @csrf
                                    <button class="btn btn-warning w-100 btn-text-icon" data-loading-text="{{ __('Ending break...') }}"><i class="mdi mdi-play-circle-outline"></i><span>{{ __('End break') }}</span></button>
                                </form>
                                <div class="section-note mb-3">{{ __('End the active break before clocking out.') }}</div>
                            @else
                                <form method="POST" action="{{ route('admin.workforce.break-start') }}" data-submit-loading class="mb-3">
                                    @csrf
                                    <label class="form-label fw-semibold">{{ __('Break note') }}</label>
                                    <input type="text" name="notes" class="form-control mb-3" maxlength="1000" placeholder="{{ __('Optional break note') }}">
                                    <button class="btn btn-light border w-100 btn-text-icon" data-loading-text="{{ __('Starting break...') }}"><i class="mdi mdi-pause-circle-outline"></i><span>{{ __('Start break') }}</span></button>
                                </form>

                                <form method="POST" action="{{ route('admin.workforce.clock-out') }}" data-submit-loading>
                                    @csrf
                                    <label class="form-label fw-semibold">{{ __('Clock-out notes') }}</label>
                                    <textarea name="notes" rows="3" class="form-control mb-3" maxlength="1000" placeholder="{{ __('Optional handover or attendance note') }}"></textarea>
                                    <button class="btn btn-primary w-100 btn-text-icon" data-loading-text="{{ __('Clocking out...') }}"><i class="mdi mdi-clock-out"></i><span>{{ __('Clock out') }}</span></button>
                                </form>
                            @endif
                        @elseif($employee->canClockTime())
                            <form method="POST" action="{{ route('admin.workforce.clock-in') }}" data-submit-loading>
                                @csrf
                                <label class="form-label fw-semibold">{{ __('Clock-in notes') }}</label>
                                <textarea name="notes" rows="3" class="form-control mb-3" maxlength="1000" placeholder="{{ __('Optional note for this attendance session') }}"></textarea>
                                <button class="btn btn-primary w-100 btn-text-icon" data-loading-text="{{ __('Clocking in...') }}"><i class="mdi mdi-clock-in"></i><span>{{ __('Clock in') }}</span></button>
                            </form>
                        @else
                            <div class="alert alert-warning border-0 rounded-4 mb-0">{{ __('Your current employee status does not allow a new attendance session.') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="admin-table-toolbar">
                            <div>
                                <h4 class="mb-1">{{ __('Recent attendance') }}</h4>
                                <div class="text-muted small">{{ __('Recorded time is preserved. Approved corrections are shown as effective time.') }}</div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Recorded') }}</th>
                                        <th>{{ __('Effective') }}</th>
                                        <th>{{ __('Breaks') }}</th>
                                        <th>{{ __('Net worked') }}</th>
                                        <th>{{ __('Correction') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentSessions as $session)
                                        @php
                                            $net = $session->netWorkedMinutes();
                                            $netHours = intdiv($net, 60);
                                            $netMinutes = $net % 60;
                                            $breakMinutes = $session->breakMinutes();
                                        @endphp
                                        <tr>
                                            <td>
                                                <div>{{ $session->clock_in_at->format('d M Y H:i') }}</div>
                                                <div class="text-muted small">→ {{ $session->clock_out_at?->format('d M Y H:i') ?: __('Open now') }}</div>
                                            </td>
                                            <td>
                                                <div class="{{ $session->hasApprovedCorrection() ? 'fw-semibold' : '' }}">{{ $session->effectiveClockInAt()->format('d M Y H:i') }}</div>
                                                <div class="text-muted small">→ {{ $session->effectiveClockOutAt()?->format('d M Y H:i') ?: __('Open now') }}</div>
                                                @if($session->hasApprovedCorrection())
                                                    <span class="badge admin-status-badge badge-soft-success mt-1">{{ __('Corrected') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ __(':minutes min', ['minutes' => $breakMinutes]) }}</td>
                                            <td>{{ __(':hours h :minutes m', ['hours' => $netHours, 'minutes' => $netMinutes]) }}</td>
                                            <td>
                                                @if($session->isOpen())
                                                    <span class="text-muted small">{{ __('Available after clock-out') }}</span>
                                                @elseif($session->pendingCorrection)
                                                    <span class="badge admin-status-badge badge-soft-warning">{{ __('Pending review') }}</span>
                                                @else
                                                    <a href="{{ route('admin.workforce.corrections.create', $session) }}" class="btn btn-sm btn-light border">{{ __('Request correction') }}</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No attendance sessions yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
