<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeProfile;
use App\Services\Workforce\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService)
    {
    }

    public function index(Request $request)
    {
        $status = (string) $request->string('status');
        $date = trim((string) $request->string('date'));

        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => in_array($status, ['open', 'closed'], true) ? $status : '',
            'date' => $date,
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
        ];

        $sessions = EmployeeAttendanceSession::query()
            ->with('employee.user')
            ->when($filters['search'], function ($query, $search) {
                $query->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] === 'open', fn ($query) => $query->whereNull('clock_out_at'))
            ->when($filters['status'] === 'closed', fn ($query) => $query->whereNotNull('clock_out_at'))
            ->when($filters['date'], fn ($query, $value) => $query->whereDate('clock_in_at', $value))
            ->latest('clock_in_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.workforce.attendance._results', compact('sessions', 'filters'));
        }

        $stats = [
            'open_now' => EmployeeAttendanceSession::whereNull('clock_out_at')->count(),
            'clock_ins_today' => EmployeeAttendanceSession::whereDate('clock_in_at', today())->count(),
            'completed_today' => EmployeeAttendanceSession::whereDate('clock_in_at', today())->whereNotNull('clock_out_at')->count(),
            'employees' => EmployeeProfile::count(),
        ];

        return view('admin.workforce.attendance.index', compact('sessions', 'filters', 'stats'));
    }

    public function timeClock(Request $request)
    {
        $employee = $request->user()->employeeProfile()
            ->with('openAttendanceSession')
            ->first();

        $recentSessions = $employee
            ? $employee->attendanceSessions()->latest('clock_in_at')->take(10)->get()
            : collect();

        return view('admin.workforce.time-clock', compact('employee', 'recentSessions'));
    }

    public function clockIn(Request $request)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->attendanceService->clockIn($request->user(), $data['notes'] ?? null);

        return redirect()
            ->route('admin.workforce.time-clock')
            ->with('success', __('You are clocked in.'));
    }

    public function clockOut(Request $request)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->attendanceService->clockOut($request->user(), $data['notes'] ?? null);

        return redirect()
            ->route('admin.workforce.time-clock')
            ->with('success', __('You are clocked out.'));
    }
}
