<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Attendance sessions') }}</h4>
                <div class="text-muted small">{{ __('Showing :count session(s) on this page.', ['count' => $sessions->count()]) }}</div>
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
                        <th>{{ __('Department') }}</th>
                        <th>{{ __('Clock in') }}</th>
                        <th>{{ __('Clock out') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        @php
                            $duration = $session->durationMinutes();
                            $hours = intdiv($duration, 60);
                            $minutes = $duration % 60;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $session->employee?->user?->name ?: __('Missing account') }}</div>
                                <div class="text-muted small">{{ $session->employee?->employee_code ?: '—' }}</div>
                            </td>
                            <td>{{ $session->employee?->department ?: '—' }}</td>
                            <td>{{ optional($session->clock_in_at)->format('d M Y H:i') ?: '—' }}</td>
                            <td>
                                @if($session->clock_out_at)
                                    {{ $session->clock_out_at->format('d M Y H:i') }}
                                @else
                                    <span class="badge admin-status-badge badge-soft-success">{{ __('Open now') }}</span>
                                @endif
                            </td>
                            <td>{{ __(':hours h :minutes m', ['hours' => $hours, 'minutes' => $minutes]) }}</td>
                            <td>{{ IlluminateSupportStr::headline($session->source) }}</td>
                            <td>
                                <div class="small">{{ $session->clock_in_notes ?: '—' }}</div>
                                @if($session->clock_out_notes)<div class="text-muted small mt-1">{{ $session->clock_out_notes }}</div>@endif
                            </td>
                        </tr>
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
