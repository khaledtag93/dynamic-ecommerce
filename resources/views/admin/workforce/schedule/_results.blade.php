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
                        <th>{{ __('Start variance') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shifts as $shift)
                        @php
                            $attendance = $attendanceByShift->get($shift->id);
                            $scheduledMinutes = $shift->durationMinutes();
                            $scheduledHours = intdiv($scheduledMinutes, 60);
                            $scheduledRemainder = $scheduledMinutes % 60;
                            $startDelta = $attendance ? $shift->starts_at->diffInMinutes($attendance->clock_in_at, false) : null;
                            $shiftEnded = $shift->ends_at->isPast();

                            $statusClass = match($shift->status) {
                                AppModelsEmployeeWorkShift::STATUS_PUBLISHED => 'badge-soft-success',
                                AppModelsEmployeeWorkShift::STATUS_CANCELLED => 'badge-soft-danger',
                                default => 'badge-soft-secondary',
                            };
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
                            <td><span class="badge admin-status-badge {{ $statusClass }}">{{ AppModelsEmployeeWorkShift::statusOptions()[$shift->status] ?? IlluminateSupportStr::headline($shift->status) }}</span></td>
                            <td>
                                @if($shift->isCancelled())
                                    <span class="text-muted">—</span>
                                @elseif($attendance)
                                    <div class="fw-semibold">{{ $attendance->clock_in_at->format('H:i') }} → {{ $attendance->clock_out_at?->format('H:i') ?: __('Open now') }}</div>
                                    <div class="text-muted small">{{ $attendance->clock_in_at->format('d M Y') }}</div>
                                @elseif($shiftEnded && $shift->isPublished())
                                    <span class="badge admin-status-badge badge-soft-danger">{{ __('No attendance recorded') }}</span>
                                @elseif($shift->isPublished())
                                    <span class="badge admin-status-badge badge-soft-secondary">{{ __('Not started') }}</span>
                                @else
                                    <span class="text-muted">{{ __('Draft schedule') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($attendance && !$shift->isCancelled())
                                    @if(abs($startDelta) <= 5)
                                        <span class="badge admin-status-badge badge-soft-success">{{ __('On time') }}</span>
                                    @elseif($startDelta > 5)
                                        <span class="badge admin-status-badge badge-soft-warning">{{ __(':minutes min late', ['minutes' => abs($startDelta)]) }}</span>
                                    @else
                                        <span class="badge admin-status-badge badge-soft-secondary">{{ __(':minutes min early', ['minutes' => abs($startDelta)]) }}</span>
                                    @endif
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
                                <td colspan="7" class="pt-0 border-top-0">
                                    <div class="small text-muted"><strong>{{ __('Shift note') }}:</strong> {{ $shift->notes }}</div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="py-5">
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
