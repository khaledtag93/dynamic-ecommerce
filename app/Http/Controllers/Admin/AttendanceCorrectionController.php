<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendanceCorrection;
use App\Models\EmployeeAttendanceSession;
use App\Services\Workforce\AttendanceCorrectionService;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    public function __construct(protected AttendanceCorrectionService $correctionService)
    {
    }

    public function create(Request $request, EmployeeAttendanceSession $employeeAttendanceSession)
    {
        $employee = $request->user()->employeeProfile()->first();

        abort_unless(
            $employee && (int) $employeeAttendanceSession->employee_profile_id === (int) $employee->id,
            403
        );

        abort_if($employeeAttendanceSession->isOpen(), 422);

        $employeeAttendanceSession->load(['approvedCorrection', 'pendingCorrection', 'breaks']);

        return view('admin.workforce.corrections.create', [
            'session' => $employeeAttendanceSession,
        ]);
    }

    public function store(Request $request, EmployeeAttendanceSession $employeeAttendanceSession)
    {
        $data = $request->validate([
            'requested_clock_in_at' => ['required', 'date'],
            'requested_clock_out_at' => ['required', 'date', 'after:requested_clock_in_at'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->correctionService->request(
            $request->user(),
            $employeeAttendanceSession,
            $data['requested_clock_in_at'],
            $data['requested_clock_out_at'],
            $data['reason'],
        );

        return redirect()
            ->route('admin.workforce.time-clock')
            ->with('success', __('Attendance correction request submitted.'));
    }

    public function index(Request $request)
    {
        $status = (string) $request->string('status');

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => array_key_exists($status, EmployeeAttendanceCorrection::statusOptions()) ? $status : '',
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
        ];

        $corrections = EmployeeAttendanceCorrection::query()
            ->with(['employee.user', 'attendanceSession', 'reviewedBy'])
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
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('created_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.workforce.corrections._results', compact('corrections', 'filters'));
        }

        $stats = [
            'pending' => EmployeeAttendanceCorrection::where('status', EmployeeAttendanceCorrection::STATUS_PENDING)->count(),
            'approved' => EmployeeAttendanceCorrection::where('status', EmployeeAttendanceCorrection::STATUS_APPROVED)->count(),
            'rejected' => EmployeeAttendanceCorrection::where('status', EmployeeAttendanceCorrection::STATUS_REJECTED)->count(),
            'total' => EmployeeAttendanceCorrection::count(),
        ];

        return view('admin.workforce.corrections.index', compact('corrections', 'filters', 'stats'));
    }

    public function approve(Request $request, EmployeeAttendanceCorrection $employeeAttendanceCorrection)
    {
        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->correctionService->approve(
            $employeeAttendanceCorrection,
            $request->user(),
            $data['review_notes'] ?? null,
        );

        return redirect()
            ->route('admin.workforce.corrections.index')
            ->with('success', __('Attendance correction approved.'));
    }

    public function reject(Request $request, EmployeeAttendanceCorrection $employeeAttendanceCorrection)
    {
        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->correctionService->reject(
            $employeeAttendanceCorrection,
            $request->user(),
            $data['review_notes'] ?? null,
        );

        return redirect()
            ->route('admin.workforce.corrections.index')
            ->with('success', __('Attendance correction rejected.'));
    }
}
