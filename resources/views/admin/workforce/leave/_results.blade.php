<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Leave requests') }}</h4>
                <div class="text-muted small">{{ __('Pending requests stay first. Approval requires enough balance and no unresolved work-shift conflict.') }}</div>
            </div>
            @if($filters['search'] || $filters['status'] || $filters['type'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Leave') }}</th>
                        <th>{{ __('Dates') }}</th>
                        <th>{{ __('Days') }}</th>
                        <th>{{ __('Schedule') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Review') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $leave)
                        @php
                            $conflicts = (int)$scheduleConflicts->get($leave->id, 0);
                            $statusClass = match($leave->status) {
                                \App\Models\EmployeeLeaveRequest::STATUS_APPROVED => 'badge-soft-success',
                                \App\Models\EmployeeLeaveRequest::STATUS_REJECTED => 'badge-soft-danger',
                                \App\Models\EmployeeLeaveRequest::STATUS_CANCELLED => 'badge-soft-secondary',
                                default => 'badge-soft-warning',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $leave->employee?->user?->name ?: __('Missing account') }}</div>
                                <div class="text-muted small">{{ $leave->employee?->employee_code ?: '—' }}@if($leave->employee?->department) · {{ $leave->employee->department }}@endif</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $leave->leaveType?->displayName() }}</div>
                                <div class="text-muted small">{{ $leave->leaveType?->is_paid ? __('Paid') : __('Unpaid') }}</div>
                                @if($leave->reason)<div class="text-muted small mt-1">{{ $leave->reason }}</div>@endif
                            </td>
                            <td>{{ $leave->starts_on->format('d M Y') }} → {{ $leave->ends_on->format('d M Y') }}</td>
                            <td>{{ number_format((float)$leave->requested_days, 2) }}</td>
                            <td>
                                @if($conflicts > 0)
                                    <span class="badge admin-status-badge badge-soft-warning">{{ trans_choice(':count shift conflict|:count shift conflicts', $conflicts, ['count' => $conflicts]) }}</span>
                                    <div class="text-muted small mt-1">{{ __('Move or cancel these shifts before approval.') }}</div>
                                @else
                                    <span class="badge admin-status-badge badge-soft-success">{{ __('No schedule conflict') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge admin-status-badge {{ $statusClass }}">{{ \App\Models\EmployeeLeaveRequest::statusOptions()[$leave->status] ?? \Illuminate\Support\Str::headline($leave->status) }}</span>
                                @if($leave->review_notes)<div class="text-muted small mt-1">{{ $leave->review_notes }}</div>@endif
                                @if($leave->reviewedBy)<div class="text-muted small">{{ $leave->reviewedBy->name }}</div>@endif
                            </td>
                            <td style="min-width: 260px">
                                @if($leave->isPending() && auth()->user()?->hasPermission('workforce.manage'))
                                    <form method="POST" action="{{ route('admin.workforce.leave.approve', $leave) }}" class="mb-2" data-submit-loading>
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="review_notes" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="{{ __('Optional review note') }}">
                                        <button class="btn btn-sm btn-success w-100" data-loading-text="{{ __('Approving...') }}">{{ __('Approve') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.workforce.leave.reject', $leave) }}" data-submit-loading>
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
                        <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No leave requests found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($requests->hasPages())<div class="mt-4">{{ $requests->links() }}</div>@endif
</div>
