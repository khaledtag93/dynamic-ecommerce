<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Attendance sessions') }}</h4>
                <div class="text-muted small">{{ __('Recorded time stays immutable; approved corrections drive effective and net worked time.') }}</div>
            </div>
            @if($filters['search'] || $filters['status'] || $filters['date'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Recorded') }}</th>
                        <th>{{ __('Effective') }}</th>
                        <th>{{ __('Breaks') }}</th>
                        <th>{{ __('Net worked') }}</th>
                        <th>{{ __('Correction') }}</th>
                        <th>{{ __('Source') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        @php
                            $net = $session->netWorkedMinutes();
                            $netHours = intdiv($net, 60);
                            $netMinutes = $net % 60;
                            $breakMinutes = $session->breakMinutes();
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $session->employee?->user?->name ?: __('Missing account') }}</div>
                                <div class="text-muted small">{{ $session->employee?->employee_code ?: '—' }}@if($session->employee?->department) · {{ $session->employee->department }}@endif</div>
                            </td>
                            <td>
                                <div>{{ $session->clock_in_at?->format('d M Y H:i') ?: '—' }}</div>
                                <div class="text-muted small">→ {{ $session->clock_out_at?->format('d M Y H:i') ?: __('Open now') }}</div>
                            </td>
                            <td>
                                <div class="{{ $session->hasApprovedCorrection() ? 'fw-semibold' : '' }}">{{ $session->effectiveClockInAt()?->format('d M Y H:i') ?: '—' }}</div>
                                <div class="text-muted small">→ {{ $session->effectiveClockOutAt()?->format('d M Y H:i') ?: __('Open now') }}</div>
                                @if($session->hasApprovedCorrection())
                                    <span class="badge admin-status-badge badge-soft-success mt-1">{{ __('Corrected') }}</span>
                                @endif
                            </td>
                            <td>{{ __(':minutes min', ['minutes' => $breakMinutes]) }}</td>
                            <td>{{ __(':hours h :minutes m', ['hours' => $netHours, 'minutes' => $netMinutes]) }}</td>
                            <td>
                                @if($session->pendingCorrection)
                                    <span class="badge admin-status-badge badge-soft-warning">{{ __('Pending review') }}</span>
                                @elseif($session->hasApprovedCorrection())
                                    <span class="badge admin-status-badge badge-soft-success">{{ __('Approved correction') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ \Illuminate\Support\Str::headline($session->source) }}</td>
                        </tr>
                        @if($session->clock_in_notes || $session->clock_out_notes)
                            <tr>
                                <td colspan="7" class="pt-0 border-top-0">
                                    <div class="small text-muted">
                                        @if($session->clock_in_notes)<strong>{{ __('Clock-in note') }}:</strong> {{ $session->clock_in_notes }}@endif
                                        @if($session->clock_out_notes)<span class="ms-3"><strong>{{ __('Clock-out note') }}:</strong> {{ $session->clock_out_notes }}</span>@endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="py-5">
                                <div class="admin-empty-state py-4">
                                    <div class="empty-icon"><i class="mdi mdi-calendar-clock-outline"></i></div>
                                    <h5 class="mb-2">{{ __('No attendance sessions found') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Attendance will appear here after employees start using the time clock.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($sessions->hasPages())<div class="mt-4">{{ $sessions->links() }}</div>@endif
</div>
