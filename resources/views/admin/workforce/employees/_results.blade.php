<div data-live-results aria-busy="false">
<div class="admin-card">
    <div class="admin-card-body">
        <div class="admin-table-toolbar">
            <div>
                <h4 class="mb-1">{{ __('Employee directory') }}</h4>
                <div class="text-muted small">{{ __('Showing :count employee(s) on this page.', ['count' => $employees->count()]) }}</div>
            </div>
            @if($filters['search'] || $filters['status'] || $filters['employment_type'] || $filters['department'])
                <span class="admin-chip">{{ __('Filtered results') }}</span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Department') }}</th>
                        <th>{{ __('Employment') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Attendance') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $employee->user?->name ?: __('Missing account') }}</div>
                                <div class="text-muted small">{{ $employee->employee_code }} · {{ $employee->user?->email ?: '—' }}</div>
                                @if($employee->phone)<div class="text-muted small">{{ $employee->phone }}</div>@endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $employee->user?->primaryRoleName() ?: '—' }}</div>
                                <div class="text-muted small">{{ $employee->job_title ?: __('No job title') }}</div>
                            </td>
                            <td>{{ $employee->department ?: '—' }}</td>
                            <td>
                                <div class="fw-semibold">{{ \App\Models\EmployeeProfile::employmentTypeOptions()[$employee->employment_type] ?? \Illuminate\Support\Str::headline($employee->employment_type) }}</div>
                                <div class="text-muted small">{{ $employee->hire_date ? __('Since :date', ['date' => $employee->hire_date->format('d M Y')]) : __('Hire date not set') }}</div>
                            </td>
                            <td>
                                @php($statusClass = match($employee->status) {
                                    \App\Models\EmployeeProfile::STATUS_ACTIVE => 'badge-soft-success',
                                    \App\Models\EmployeeProfile::STATUS_ON_LEAVE => 'badge-soft-warning',
                                    \App\Models\EmployeeProfile::STATUS_TERMINATED => 'badge-soft-danger',
                                    default => 'badge-soft-secondary',
                                })
                                <span class="badge admin-status-badge {{ $statusClass }}">{{ \App\Models\EmployeeProfile::statusOptions()[$employee->status] ?? \Illuminate\Support\Str::headline($employee->status) }}</span>
                            </td>
                            <td>
                                @if($employee->openAttendanceSession)
                                    <span class="badge admin-status-badge badge-soft-success">{{ __('Clocked in') }}</span>
                                    <div class="text-muted small mt-1">{{ $employee->openAttendanceSession->clock_in_at->format('d M Y H:i') }}</div>
                                @else
                                    <span class="badge admin-status-badge badge-soft-secondary">{{ __('Clocked out') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(auth()->user()?->hasPermission('workforce.manage'))
                                    <a href="{{ route('admin.workforce.employees.edit', $employee) }}" class="btn-table-icon btn-edit" title="{{ __('Edit employee') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                @else
                                    <span class="text-muted small">{{ __('View only') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5">
                                <div class="admin-empty-state py-4">
                                    <div class="empty-icon"><i class="mdi mdi-account-group-outline"></i></div>
                                    <h5 class="mb-2">{{ __('No employees found') }}</h5>
                                    <p class="text-muted mb-0">{{ __('Create employee profiles for staff accounts or adjust the filters.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($employees->hasPages())<div class="mt-4">{{ $employees->links() }}</div>@endif
</div>
