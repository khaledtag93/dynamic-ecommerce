<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveAdjustment;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Services\Workforce\LeaveBalanceService;
use App\Services\Workforce\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveBalanceService $balanceService,
        protected LeaveRequestService $leaveRequestService,
    ) {
    }

    public function myLeave(Request $request)
    {
        $employee = $request->user()->employeeProfile()->first();
        $year = max(2000, min(2100, (int) $request->integer('year', now()->year)));

        $balances = $employee
            ? $this->balanceService->balancesForEmployee($employee, $year)
            : collect();

        $requests = $employee
            ? $employee->leaveRequests()
                ->with(['leaveType', 'reviewedBy'])
                ->latest('created_at')
                ->take(30)
                ->get()
            : collect();

        $leaveTypes = EmployeeLeaveType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.workforce.leave.my-leave', compact(
            'employee',
            'year',
            'balances',
            'requests',
            'leaveTypes',
        ));
    }

    public function requestLeave(Request $request)
    {
        $data = $request->validate([
            'employee_leave_type_id' => ['required', 'integer', Rule::exists('employee_leave_types', 'id')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = EmployeeLeaveType::query()->findOrFail((int) $data['employee_leave_type_id']);

        $this->leaveRequestService->request(
            $request->user(),
            $type,
            $data['starts_on'],
            $data['ends_on'],
            $data['reason'] ?? null,
        );

        return redirect()
            ->route('admin.workforce.my-leave')
            ->with('success', __('Leave request submitted.'));
    }

    public function cancelLeave(Request $request, EmployeeLeaveRequest $employeeLeaveRequest)
    {
        $this->leaveRequestService->cancel($employeeLeaveRequest, $request->user());

        return redirect()
            ->route('admin.workforce.my-leave')
            ->with('success', __('Leave request cancelled.'));
    }

    public function index(Request $request)
    {
        $status = (string) $request->string('status');
        $typeId = (int) $request->integer('type');

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => array_key_exists($status, EmployeeLeaveRequest::statusOptions()) ? $status : '',
            'type' => $typeId > 0 ? $typeId : 0,
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
        ];

        $requests = EmployeeLeaveRequest::query()
            ->with(['employee.user', 'leaveType', 'reviewedBy'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('employee_code', 'like', "%{$search}%")
                                ->orWhere('department', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($userQuery) use ($search) {
                                    $userQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($filters['status'], fn ($query, $value) => $query->where('status', $value))
            ->when($filters['type'], fn ($query, $value) => $query->where('employee_leave_type_id', $value))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('created_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $scheduleConflicts = $this->scheduleConflictsForRequests($requests->getCollection());

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.workforce.leave._results', compact('requests', 'filters', 'scheduleConflicts'));
        }

        $stats = [
            'pending' => EmployeeLeaveRequest::where('status', EmployeeLeaveRequest::STATUS_PENDING)->count(),
            'approved' => EmployeeLeaveRequest::where('status', EmployeeLeaveRequest::STATUS_APPROVED)->count(),
            'rejected' => EmployeeLeaveRequest::where('status', EmployeeLeaveRequest::STATUS_REJECTED)->count(),
            'current' => EmployeeLeaveRequest::query()
                ->where('status', EmployeeLeaveRequest::STATUS_APPROVED)
                ->whereDate('starts_on', '<=', today())
                ->whereDate('ends_on', '>=', today())
                ->count(),
        ];

        $leaveTypes = EmployeeLeaveType::query()->orderBy('name')->get();

        return view('admin.workforce.leave.index', compact(
            'requests',
            'filters',
            'scheduleConflicts',
            'stats',
            'leaveTypes',
        ));
    }

    public function approve(Request $request, EmployeeLeaveRequest $employeeLeaveRequest)
    {
        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->leaveRequestService->approve(
            $employeeLeaveRequest,
            $request->user(),
            $data['review_notes'] ?? null,
        );

        return redirect()
            ->route('admin.workforce.leave.index')
            ->with('success', __('Leave request approved.'));
    }

    public function reject(Request $request, EmployeeLeaveRequest $employeeLeaveRequest)
    {
        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->leaveRequestService->reject(
            $employeeLeaveRequest,
            $request->user(),
            $data['review_notes'] ?? null,
        );

        return redirect()
            ->route('admin.workforce.leave.index')
            ->with('success', __('Leave request rejected.'));
    }

    public function adjustmentForm()
    {
        return view('admin.workforce.leave.adjustment', [
            'employees' => EmployeeProfile::query()->with('user')->orderBy('employee_code')->get(),
            'leaveTypes' => EmployeeLeaveType::query()->where('is_active', true)->orderBy('name')->get(),
            'year' => now()->year,
            'adjustments' => EmployeeLeaveAdjustment::query()
                ->with(['employee.user', 'leaveType', 'createdBy'])
                ->latest('created_at')
                ->take(50)
                ->get(),
        ]);
    }

    public function adjustBalance(Request $request)
    {
        $data = $request->validate([
            'employee_profile_id' => ['required', 'integer', Rule::exists('employee_profiles', 'id')],
            'employee_leave_type_id' => ['required', 'integer', Rule::exists('employee_leave_types', 'id')],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'type' => ['required', Rule::in(array_keys(EmployeeLeaveAdjustment::typeOptions()))],
            'days' => ['required', 'numeric', 'between:-365,365', 'not_in:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $employee = EmployeeProfile::query()->findOrFail((int) $data['employee_profile_id']);
        $type = EmployeeLeaveType::query()->findOrFail((int) $data['employee_leave_type_id']);

        $this->leaveRequestService->adjustBalance(
            $employee,
            $type,
            (int) $data['year'],
            $data['type'],
            (float) $data['days'],
            $data['reason'],
            $request->user(),
        );

        return redirect()
            ->route('admin.workforce.leave.index')
            ->with('success', __('Leave balance adjustment recorded.'));
    }

    private function scheduleConflictsForRequests(Collection $requests): Collection
    {
        if ($requests->isEmpty()) {
            return collect();
        }

        $employeeIds = $requests->pluck('employee_profile_id')->unique()->values();
        $minimumStart = $requests->min('starts_on')->copy()->startOfDay();
        $maximumEnd = $requests->max('ends_on')->copy()->addDay()->startOfDay();

        $shifts = EmployeeWorkShift::query()
            ->whereIn('employee_profile_id', $employeeIds)
            ->where('status', '!=', EmployeeWorkShift::STATUS_CANCELLED)
            ->where('starts_at', '<', $maximumEnd)
            ->where('ends_at', '>', $minimumStart)
            ->get()
            ->groupBy('employee_profile_id');

        return $requests->mapWithKeys(function (EmployeeLeaveRequest $leave) use ($shifts) {
            $start = $leave->starts_on->copy()->startOfDay();
            $end = $leave->ends_on->copy()->addDay()->startOfDay();

            $count = $shifts->get($leave->employee_profile_id, collect())
                ->filter(fn (EmployeeWorkShift $shift) => $shift->starts_at->lt($end) && $shift->ends_at->gt($start))
                ->count();

            return [$leave->id => $count];
        });
    }
}
