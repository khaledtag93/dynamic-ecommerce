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
        $workforceClock = \App\Support\WorkforceClock::class;
        $date = trim((string) $request->string('date'));

        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }

        $filters = [
            'search' => mb_substr(trim((string) $request->string('search')), 0, 100),
            'status' => in_array($status, ['open', 'closed', 'voided'], true) ? $status : '',
            'date' => $date,
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
        ];

        $sessions = EmployeeAttendanceSession::query()
            ->with(['employee.user', 'breaks', 'approvedCorrection', 'pendingCorrection'])
            ->when($filters['search'], function ($query, $search) {
                $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $like = '%' . $escapedSearch . '%';

                $query->whereHas('employee', function ($employeeQuery) use ($like) {
                    $employeeQuery->where('employee_code', 'like', $like)
                        ->orWhere('department', 'like', $like)
                        ->orWhereHas('user', function ($userQuery) use ($like) {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->when($filters['status'] === 'open', fn ($query) => $query->whereNull('clock_out_at')->whereNull('voided_at'))
            ->when($filters['status'] === 'closed', fn ($query) => $query->whereNotNull('clock_out_at')->whereNull('voided_at'))
            ->when($filters['status'] === 'voided', fn ($query) => $query->whereNotNull('voided_at'))
            ->when($filters['date'], function ($query, $value) use ($workforceClock) {
                [$from, $to] = $workforceClock::localDayUtcBounds($value);
                $query->where('clock_in_at', '>=', $from)->where('clock_in_at', '<', $to);
            })
            ->latest('clock_in_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.workforce.attendance._results', compact('sessions', 'filters'));
        }

        $stats = [
            'open_now' => EmployeeAttendanceSession::whereNull('clock_out_at')->whereNull('voided_at')->count(),
            'clock_ins_today' => EmployeeAttendanceSession::whereNull('voided_at')->where('clock_in_at', '>=', $workforceClock::localDayUtcBounds($workforceClock::today())[0])->where('clock_in_at', '<', $workforceClock::localDayUtcBounds($workforceClock::today())[1])->count(),
            'completed_today' => EmployeeAttendanceSession::whereNull('voided_at')->where('clock_in_at', '>=', $workforceClock::localDayUtcBounds($workforceClock::today())[0])->where('clock_in_at', '<', $workforceClock::localDayUtcBounds($workforceClock::today())[1])->whereNotNull('clock_out_at')->count(),
            'employees' => EmployeeProfile::count(),
        ];

        return view('admin.workforce.attendance.index', compact('sessions', 'filters', 'stats'));
    }

    public function voidSession(Request $request, EmployeeAttendanceSession $employeeAttendanceSession, \App\Services\Workforce\AttendanceVoidService $service)
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $service->void($employeeAttendanceSession, $request->user(), $data['void_reason']);

        return redirect()->route('admin.workforce.attendance.index')
            ->with('success', __('Attendance session voided without deleting its history.'));
    }

    public function timeClock(Request $request)
    {
        $employee = $request->user()->employeeProfile()
            ->with(['openAttendanceSession.breaks', 'openAttendanceSession.openBreak'])
            ->first();

        $recentSessions = $employee
            ? $employee->attendanceSessions()
                ->with(['breaks', 'approvedCorrection', 'pendingCorrection'])
                ->latest('clock_in_at')
                ->take(10)
                ->get()
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

    public function startBreak(Request $request)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->attendanceService->startBreak($request->user(), $data['notes'] ?? null);

        return redirect()
            ->route('admin.workforce.time-clock')
            ->with('success', __('Break started.'));
    }

    public function endBreak(Request $request)
    {
        $this->attendanceService->endBreak($request->user());

        return redirect()
            ->route('admin.workforce.time-clock')
            ->with('success', __('Break ended.'));
    }
}
