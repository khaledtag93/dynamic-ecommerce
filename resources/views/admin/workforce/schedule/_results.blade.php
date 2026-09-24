<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Scheduled work shifts') }}</h4>
                <div class="text-muted small">{{ __('Showing :count work shift(s) on this page.', ['count' => $shifts->count()]) }}</div>
            </div>
            @if($filters['search'] || $filters['status'] || $filters['department'] || $filters['date_from'] || $filters['date_to'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Scheduled') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actual attendance') }}</th>
                        <th>{{ __('Attendance rules') }}</th>
                        <th>{{ __('Net worked') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shifts as $shift)
                        @php
                            $attendanceData = $attendanceDataByShift->get($shift->id, []);
                            $attendance = $attendanceData['session'] ?? null;
                            $assessment = $attendanceData['assessment'] ?? [];
                            $scheduledMinutes = $shift->durationMinutes();
                            $scheduledHours = intdiv($scheduledMinutes, 60);
                            $scheduledRemainder = $scheduledMinutes % 60;

                            $statusClass = match($shift->status) {
                                \App\Models\EmployeeWorkShift::STATUS_PUBLISHED => 'badge-soft-success',
                                \App\Models\EmployeeWorkShift::STATUS_CANCELLED => 'badge-soft-danger',
                                default => 'badge-soft-secondary',
                            };

                            $startStatus = $assessment['start_status'] ?? null;
                            $endStatus = $assessment['end_status'] ?? null;
                            $state = $assessment['state'] ?? null;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $shift->employee?->user?->name ?: __('Missing account') }}</div>
                                <div class="text-muted small">{{ $shift->employee?->employee_code ?: '—' }}@if($shift->employee?->department) · {{ $shift->employee->department }}@endif</div>
                                @if($shift->employee?->job_title)<div class="text-muted small">{{ $shift->employee->job_title }}</div>@endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $shift->starts_at->format('d M Y') }}</div>
                                <div>{{ $shift->starts_at->format('H:i') }} → {{ $shift->ends_at->format('H:i') }}</div>
                                <div class="text-muted small">{{ __(':hours h :minutes m', ['hours' => $scheduledHours, 'minutes' => $scheduledRemainder]) }}</div>
                            </td>
                            <td>{{ $shift->location ?: '—' }}</td>
                            <td>
                                <span class="badge admin-status-badge {{ $statusClass }}">{{ \App\Models\EmployeeWorkShift::statusOptions()[$shift->status] ?? \Illuminate\Support\Str::headline($shift->status) }}</span>
                            </td>
                            <td>
                                @if($attendance)
                                    <div class="fw-semibold">{{ $attendance->effectiveClockInAt()->format('H:i') }} → {{ $attendance->effectiveClockOutAt()?->format('H:i') ?: __('Open now') }}</div>
                                    <div class="text-muted small">{{ $attendance->effectiveClockInAt()->format('d M Y') }}</div>
                                    @if($attendance->hasApprovedCorrection())
                                        <span class="badge admin-status-badge badge-soft-success mt-1">{{ __('Corrected') }}</span>
                                    @endif
                                @elseif($state === 'absent')
                                    <span class="badge admin-status-badge badge-soft-danger">{{ __('Absent') }}</span>
                                @elseif($state === 'not_clocked_in')
                                    <span class="badge admin-status-badge badge-soft-warning">{{ __('Not clocked in') }}</span>
                                @elseif($state === 'upcoming')
                                    <span class="badge admin-status-badge badge-soft-secondary">{{ __('Upcoming') }}</span>
                                @elseif($state === 'draft')
                                    <span class="text-muted">{{ __('Draft schedule') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($attendance)
                                    <div class="d-flex flex-column gap-1 align-items-start">
                                        @if($startStatus === 'on_time')
                                            <span class="badge admin-status-badge badge-soft-success">{{ __('On time') }}</span>
                                        @elseif($startStatus === 'late')
                                            <span class="badge admin-status-badge badge-soft-warning">{{ __(':minutes min late', ['minutes' => abs((int)($assessment['start_delta_minutes'] ?? 0))]) }}</span>
                                        @elseif($startStatus === 'early')
                                            <span class="badge admin-status-badge badge-soft-secondary">{{ __(':minutes min early', ['minutes' => abs((int)($assessment['start_delta_minutes'] ?? 0))]) }}</span>
                                        @endif

                                        @if($endStatus === 'early_departure')
                                            <span class="badge admin-status-badge badge-soft-warning">{{ __('Left :minutes min early', ['minutes' => abs((int)($assessment['end_delta_minutes'] ?? 0))]) }}</span>
                                        @elseif($endStatus === 'after_shift')
                                            <span class="badge admin-status-badge badge-soft-secondary">{{ __('Stayed :minutes min after shift', ['minutes' => abs((int)($assessment['end_delta_minutes'] ?? 0))]) }}</span>
                                        @elseif($endStatus === 'open')
                                            <span class="badge admin-status-badge badge-soft-success">{{ __('Session open') }}</span>
                                        @endif
                                    </div>
                                @elseif($state === 'absent')
                                    <span class="badge admin-status-badge badge-soft-danger">{{ __('Absence') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($attendance)
                                    @php
                                        $net = (int)($assessment['net_minutes'] ?? 0);
                                        $netHours = intdiv($net, 60);
                                        $netMinutes = $net % 60;
                                    @endphp
                                    <div class="fw-semibold">{{ __(':hours h :minutes m', ['hours' => $netHours, 'minutes' => $netMinutes]) }}</div>
                                    <div class="text-muted small">{{ __('Breaks: :minutes min', ['minutes' => (int)($assessment['break_minutes'] ?? 0)]) }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(auth()->user()?->hasPermission('workforce.manage') && !$shift->isCancelled())
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <a href="{{ route('admin.workforce.schedule.edit', $shift) }}" class="btn-table-icon btn-edit" title="{{ __('Edit work shift') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                        <form method="POST" action="{{ route('admin.workforce.schedule.cancel', $shift) }}" data-confirm-message="{{ __('Cancel this work shift? The historical record will be kept.') }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-table-icon btn-delete" title="{{ __('Cancel work shift') }}"><i class="mdi mdi-calendar-remove-outline"></i></button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">{{ __('View only') }}</span>
                                @endif
                            </td>
                        </tr>
                        @if($shift->notes)
                            <tr>
                                <td colspan="8" class="pt-0 border-top-0">
                                    <div class="small text-muted"><strong>{{ __('Shift note') }}:</strong> {{ $shift->notes }}</div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="py-5">
                                <div class="admin-empty-state py-4">
                                    <div class="empty-icon"><i class="mdi mdi-calendar-blank-outline"></i></div>
                                    <h5 class="mb-2">{{ __('No work shifts found') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Create a work shift or adjust the schedule filters.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($shifts->hasPages())<div class="mt-4">{{ $shifts->links() }}</div>@endif
</div>
