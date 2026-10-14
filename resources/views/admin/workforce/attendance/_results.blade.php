<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Attendance sessions') }}</h4>
                <p class="text-muted small mb-1">{{ __('Recorded time stays immutable; approved corrections drive effective and net worked time.') }}</p>
                <div class="small text-muted">{{ __('Work timezone') }}: <bdi dir="ltr">{{ \App\Support\WorkforceClock::timezone() }}</bdi> · {{ __('Original time') }} / {{ __('Approved time') }}</div>
            </div>
            @if($filters['search'] || $filters['status'] || $filters['date'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        @error('void_reason')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th title="{{ __('The unchanged original clock-in and clock-out timestamps.') }}">{{ __('Original time') }}</th>
                        <th title="{{ __('Approved corrections determine the times used for payroll.') }}">{{ __('Approved time') }}</th>
                        <th>{{ __('Breaks') }}</th>
                        <th>{{ __('Net worked') }}</th>
                        <th>{{ __('Correction') }}</th>
                        <th>{{ __('Source') }}</th>
                        @if(auth()->user()?->hasPermission('workforce.manage'))
                            <th>{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        @php
                            $net = $session->netWorkedMinutes();
                            $netHours = intdiv($net, 60);
                            $netMinutes = $net % 60;
                            $breakMinutes = $session->breakMinutes();
                            $closedBreakMinutes = (int) $session->breaks->filter(fn ($b) => $b->ends_at !== null)->sum(fn ($b) => $b->durationMinutes());
                            $openBreak = $session->breaks->first(fn ($b) => $b->ends_at === null);
                            $isLive = $session->isOpen() && ! $session->isVoided();
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $session->employee?->user?->name ?: __('Missing account') }}</div>
                                <div class="text-muted small">{{ $session->employee?->employee_code ?: '—' }}@if($session->employee?->department) · {{ $session->employee->department }}@endif</div>
                                @if($session->isVoided())
                                    <span class="badge admin-status-badge badge-soft-danger">{{ __('Voided') }}</span>
                                    <div class="text-muted small">{{ $session->void_reason }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="workforce-time"><bdi dir="ltr">{{ \App\Support\WorkforceClock::local($session->clock_in_at) ?: '—' }}</bdi></div>
                                <div class="text-muted small workforce-time"><span aria-hidden="true">→ </span><bdi dir="ltr">{{ \App\Support\WorkforceClock::local($session->clock_out_at) ?: __('Open now') }}</bdi></div>
                            </td>
                            <td>
                                <div class="workforce-time {{ $session->hasApprovedCorrection() ? 'fw-semibold' : '' }}"><bdi dir="ltr">{{ \App\Support\WorkforceClock::local($session->effectiveClockInAt()) ?: '—' }}</bdi></div>
                                <div class="text-muted small workforce-time"><span aria-hidden="true">→ </span><bdi dir="ltr">{{ \App\Support\WorkforceClock::local($session->effectiveClockOutAt()) ?: __('Open now') }}</bdi></div>
                                @if($session->hasApprovedCorrection())
                                    <span class="badge admin-status-badge badge-soft-success mt-1">{{ __('Corrected') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($isLive)
                                    <span data-workforce-live-break data-start="{{ $session->effectiveClockInAt()->getTimestamp() }}" data-closed-break="{{ $closedBreakMinutes }}" data-open-break="{{ $openBreak?->starts_at?->getTimestamp() ?? '' }}" data-break-template="{{ __(':minutes min') }}">{{ __(':minutes min', ['minutes' => $breakMinutes]) }}</span>
                                @else
                                    {{ __(':minutes min', ['minutes' => $breakMinutes]) }}
                                @endif
                            </td>
                            <td>
                                @if($isLive)
                                    <strong data-workforce-live-net data-start="{{ $session->effectiveClockInAt()->getTimestamp() }}" data-closed-break="{{ $closedBreakMinutes }}" data-open-break="{{ $openBreak?->starts_at?->getTimestamp() ?? '' }}" data-net-template="{{ __(':hours h :minutes m') }}">{{ __(':hours h :minutes m', ['hours' => $netHours, 'minutes' => $netMinutes]) }}</strong>
                                    <div class="text-muted small">{{ __('Live estimate until clock-out') }}</div>
                                @else
                                    <strong>{{ __(':hours h :minutes m', ['hours' => $netHours, 'minutes' => $netMinutes]) }}</strong>
                                @endif
                                @if($session->isVoided())<div class="text-muted small">{{ __('Excluded from payroll') }}</div>@endif
                            </td>
                            <td>
                                @if($session->isVoided())
                                    <span class="badge admin-status-badge badge-soft-danger">{{ __('Voided') }}</span>
                                @elseif($session->pendingCorrection)
                                    <span class="badge admin-status-badge badge-soft-warning">{{ __('Pending review') }}</span>
                                @elseif($session->hasApprovedCorrection())
                                    <span class="badge admin-status-badge badge-soft-success">{{ __('Approved correction') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ \Illuminate\Support\Str::headline($session->source) }}</td>
                            @if(auth()->user()?->hasPermission('workforce.manage'))
                                <td style="min-width: 190px">
                                    @if(!$session->isVoided() && !$session->isOpen() && !$session->pendingCorrection)
                                        <form method="POST" action="{{ route('admin.workforce.attendance.void', $session) }}" data-workforce-void-form data-confirm-message="{{ __('Void this attendance session? The original record will be preserved and excluded from future payroll.') }}">
                                            @csrf
                                            <label for="void-reason-{{ $session->id }}" class="form-label small mb-1">{{ __('Reason for voiding') }}</label>
                                            <input id="void-reason-{{ $session->id }}" class="form-control form-control-sm mb-2" name="void_reason" required minlength="10" maxlength="2000" placeholder="{{ __('Required audit reason') }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Void session') }}</button>
                                        </form>
                                    @else<span class="text-muted">—</span>@endif
                                </td>
                            @endif
                        </tr>
                        @if($session->clock_in_notes || $session->clock_out_notes)
                            <tr>
                                <td colspan="{{ auth()->user()?->hasPermission('workforce.manage') ? 8 : 7 }}" class="pt-0 border-top-0">
                                    <div class="small text-muted">
                                        @if($session->clock_in_notes)<strong>{{ __('Clock-in note') }}:</strong> {{ $session->clock_in_notes }}@endif
                                        @if($session->clock_out_notes)<span class="ms-3"><strong>{{ __('Clock-out note') }}:</strong> {{ $session->clock_out_notes }}</span>@endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="{{ auth()->user()?->hasPermission('workforce.manage') ? 8 : 7 }}" class="py-5 text-center text-muted">{{ __('No attendance sessions found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($sessions->hasPages())<div class="mt-4">{{ $sessions->links() }}</div>@endif
</div>
