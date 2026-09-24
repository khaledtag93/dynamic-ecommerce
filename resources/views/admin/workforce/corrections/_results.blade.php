<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Correction request history') }}</h4>
                <div class="text-muted small">{{ __('Pending requests stay at the top. Recorded attendance is never overwritten.') }}</div>
            </div>
            @if($filters['search'] || $filters['status'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Previous effective time') }}</th>
                        <th>{{ __('Requested time') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Review') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($corrections as $correction)
                        @php
                            $statusClass = match($correction->status) {
                                AppModelsEmployeeAttendanceCorrection::STATUS_APPROVED => 'badge-soft-success',
                                AppModelsEmployeeAttendanceCorrection::STATUS_REJECTED => 'badge-soft-danger',
                                default => 'badge-soft-warning',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $correction->employee?->user?->name ?: __('Missing account') }}</div>
                                <div class="text-muted small">{{ $correction->employee?->employee_code ?: '—' }}@if($correction->employee?->department) · {{ $correction->employee->department }}@endif</div>
                                <div class="text-muted small">{{ $correction->created_at?->format('d M Y H:i') }}</div>
                            </td>
                            <td>
                                <div>{{ $correction->previous_clock_in_at->format('d M Y H:i') }}</div>
                                <div class="text-muted small">→ {{ $correction->previous_clock_out_at?->format('d M Y H:i') ?: '—' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $correction->requested_clock_in_at->format('d M Y H:i') }}</div>
                                <div class="text-muted small">→ {{ $correction->requested_clock_out_at?->format('d M Y H:i') ?: '—' }}</div>
                            </td>
                            <td style="min-width: 220px">{{ $correction->reason }}</td>
                            <td>
                                <span class="badge admin-status-badge {{ $statusClass }}">{{ AppModelsEmployeeAttendanceCorrection::statusOptions()[$correction->status] ?? IlluminateSupportStr::headline($correction->status) }}</span>
                                @if($correction->reviewed_at)
                                    <div class="text-muted small mt-1">{{ $correction->reviewed_at->format('d M Y H:i') }}</div>
                                    @if($correction->reviewedBy)<div class="text-muted small">{{ $correction->reviewedBy->name }}</div>@endif
                                @endif
                                @if($correction->review_notes)<div class="text-muted small mt-1">{{ $correction->review_notes }}</div>@endif
                            </td>
                            <td style="min-width: 260px">
                                @if($correction->isPending() && auth()->user()?->hasPermission('workforce.manage'))
                                    <form method="POST" action="{{ route('admin.workforce.corrections.approve', $correction) }}" class="mb-2" data-submit-loading>
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="review_notes" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="{{ __('Optional review note') }}">
                                        <button class="btn btn-sm btn-success w-100" data-loading-text="{{ __('Approving...') }}">{{ __('Approve') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.workforce.corrections.reject', $correction) }}" data-submit-loading>
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="review_notes" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="{{ __('Optional rejection reason') }}">
                                        <button class="btn btn-sm btn-outline-danger w-100" data-loading-text="{{ __('Rejecting...') }}">{{ __('Reject') }}</button>
                                    </form>
                                @else
                                    <span class="text-muted small">{{ __('Review complete') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5">
                                <div class="admin-empty-state py-4">
                                    <div class="empty-icon"><i class="mdi mdi-file-document-edit-outline"></i></div>
                                    <h5 class="mb-2">{{ __('No correction requests found') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Employee attendance correction requests will appear here for review.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($corrections->hasPages())<div class="mt-4">{{ $corrections->links() }}</div>@endif
</div>
