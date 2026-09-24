@extends('layouts.admin')

@section('title', __('My time clock') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('My time clock')" :description="__('Clock your attendance separately from POS cash drawer shifts.')">
        <a href="{{ route('admin.workforce.my-schedule') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-account-outline"></i><span>{{ __('My schedule') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.view'))
            <a href="{{ route('admin.workforce.attendance.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-clock-outline"></i><span>{{ __('Attendance review') }}</span></a>
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
                            <span class="badge admin-status-badge {{ $employee->openAttendanceSession ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $employee->openAttendanceSession ? __('Clocked in') : __('Clocked out') }}</span>
                        </div>

                        <div class="admin-card mb-4">
                            <div class="admin-card-body">
                                @if($employee->openAttendanceSession)
                                    <div class="admin-stat-label">{{ __('Current session started') }}</div>
                                    <div class="admin-stat-value fs-3">{{ $employee->openAttendanceSession->clock_in_at->format('H:i') }}</div>
                                    <div class="text-muted small">{{ $employee->openAttendanceSession->clock_in_at->format('d M Y') }}</div>
                                @else
                                    <div class="admin-stat-label">{{ __('Current status') }}</div>
                                    <div class="admin-stat-value fs-3">{{ __('Ready to clock in') }}</div>
                                    <div class="text-muted small">{{ __('Your next attendance session starts when you confirm clock in.') }}</div>
                                @endif
                            </div>
                        </div>

                        @error('attendance')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror

                        @if($employee->openAttendanceSession)
                            <form method="POST" action="{{ route('admin.workforce.clock-out') }}" data-submit-loading>
                                @csrf
                                <label class="form-label fw-semibold">{{ __('Clock-out notes') }}</label>
                                <textarea name="notes" rows="3" class="form-control mb-3" maxlength="1000" placeholder="{{ __('Optional handover or attendance note') }}"></textarea>
                                <button class="btn btn-primary w-100 btn-text-icon" data-loading-text="{{ __('Clocking out...') }}"><i class="mdi mdi-clock-out"></i><span>{{ __('Clock out') }}</span></button>
                            </form>
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
                                <div class="text-muted small">{{ __('Your latest clock-in and clock-out sessions.') }}</div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead><tr><th>{{ __('Clock in') }}</th><th>{{ __('Clock out') }}</th><th>{{ __('Duration') }}</th><th>{{ __('Notes') }}</th></tr></thead>
                                <tbody>
                                    @forelse($recentSessions as $session)
                                        @php
                                            $duration = $session->durationMinutes();
                                            $hours = intdiv($duration, 60);
                                            $minutes = $duration % 60;
                                        @endphp
                                        <tr>
                                            <td>{{ $session->clock_in_at->format('d M Y H:i') }}</td>
                                            <td>{{ $session->clock_out_at?->format('d M Y H:i') ?: __('Open now') }}</td>
                                            <td>{{ __(':hours h :minutes m', ['hours' => $hours, 'minutes' => $minutes]) }}</td>
                                            <td>
                                                <div class="small">{{ $session->clock_in_notes ?: '—' }}</div>
                                                @if($session->clock_out_notes)<div class="text-muted small mt-1">{{ $session->clock_out_notes }}</div>@endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No attendance sessions yet.') }}</td></tr>
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
