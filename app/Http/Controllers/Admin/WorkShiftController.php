<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Services\Workforce\WorkShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class WorkShiftController extends Controller
{
    public function __construct(protected WorkShiftService $workShiftService)
    {
    }

    public function index(Request $request)
    {
        $status = (string) $request->string('status');
        $dateFrom = $this->validDate((string) $request->string('date_from'));
        $dateTo = $this->validDate((string) $request->string('date_to'));

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => array_key_exists($status, EmployeeWorkShift::statusOptions()) ? $status : '',
            'department' => trim((string) $request->string('department')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
        ];

        $shifts = EmployeeWorkShift::query()
            ->with(['employee.user'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('location', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('employee_code', 'like', "%{$search}%")
                                ->orWhere('job_title', 'like', "%{$search}%")
                                ->orWhere('department', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($userQuery) use ($search) {
                                    $userQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($filters['status'], fn ($query, $value) => $query->where('status', $value))
            ->when($filters['department'], fn ($query, $value) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('department', $value)))
            ->when($filters['date_from'], fn ($query, $value) => $query->where('ends_at', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'], fn ($query, $value) => $query->where('starts_at', '<=', $value.' 23:59:59'))
            ->orderByRaw("CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END")
            ->orderBy('starts_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $attendanceByShift = $this->attendanceMapForShifts($shifts->getCollection());

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.workforce.schedule._results', compact('shifts', 'filters', 'attendanceByShift'));
        }

        $stats = [
            'today' => EmployeeWorkShift::query()
                ->where('status', EmployeeWorkShift::STATUS_PUBLISHED)
                ->whereDate('starts_at', today())
                ->count(),
            'upcoming_7d' => EmployeeWorkShift::query()
                ->where('status', EmployeeWorkShift::STATUS_PUBLISHED)
                ->where('starts_at', '>=', now())
                ->where('starts_at', '<', now()->addDays(7))
                ->count(),
            'draft' => EmployeeWorkShift::query()->where('status', EmployeeWorkShift::STATUS_DRAFT)->count(),
            'published' => EmployeeWorkShift::query()->where('status', EmployeeWorkShift::STATUS_PUBLISHED)->count(),
        ];

        $departments = EmployeeProfile::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return view('admin.workforce.schedule.index', compact(
            'shifts',
            'filters',
            'attendanceByShift',
            'stats',
            'departments',
        ));
    }

    public function create()
    {
        return view('admin.workforce.schedule.create', [
            'shift' => new EmployeeWorkShift(),
            'employees' => $this->schedulableEmployees(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedShift($request);
        $employee = EmployeeProfile::query()->findOrFail((int) $data['employee_profile_id']);

        $shift = $this->workShiftService->create($employee, $data, $request->user());

        return redirect()
            ->route('admin.workforce.schedule.edit', $shift)
            ->with('success', __('Work shift created successfully.'));
    }

    public function edit(EmployeeWorkShift $employeeWorkShift)
    {
        $employeeWorkShift->load('employee.user');

        return view('admin.workforce.schedule.edit', [
            'shift' => $employeeWorkShift,
            'employees' => $this->schedulableEmployees($employeeWorkShift->employee_profile_id),
        ]);
    }

    public function update(Request $request, EmployeeWorkShift $employeeWorkShift)
    {
        $data = $this->validatedShift($request);

        $this->workShiftService->update($employeeWorkShift, $data, $request->user());

        return redirect()
            ->route('admin.workforce.schedule.edit', $employeeWorkShift)
            ->with('success', __('Work shift updated successfully.'));
    }

    public function cancel(Request $request, EmployeeWorkShift $employeeWorkShift)
    {
        $this->workShiftService->cancel($employeeWorkShift, $request->user());

        return redirect()
            ->route('admin.workforce.schedule.index')
            ->with('success', __('Work shift cancelled successfully.'));
    }

    public function mySchedule(Request $request)
    {
        $employee = $request->user()->employeeProfile()->first();

        $shifts = $employee
            ? $employee->workShifts()
                ->where('status', EmployeeWorkShift::STATUS_PUBLISHED)
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->take(30)
                ->get()
            : collect();

        return view('admin.workforce.my-schedule', compact('employee', 'shifts'));
    }

    private function validatedShift(Request $request): array
    {
        return $request->validate([
            'employee_profile_id' => ['required', 'integer', Rule::exists('employee_profiles', 'id')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in([
                EmployeeWorkShift::STATUS_DRAFT,
                EmployeeWorkShift::STATUS_PUBLISHED,
            ])],
            'location' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function schedulableEmployees(?int $includeEmployeeId = null)
    {
        return EmployeeProfile::query()
            ->with('user')
            ->where(function ($query) use ($includeEmployeeId) {
                $query->where('status', EmployeeProfile::STATUS_ACTIVE);

                if ($includeEmployeeId) {
                    $query->orWhereKey($includeEmployeeId);
                }
            })
            ->orderBy('employee_code')
            ->get();
    }

    private function validDate(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private function attendanceMapForShifts(Collection $shifts): Collection
    {
        if ($shifts->isEmpty()) {
            return collect();
        }

        $employeeIds = $shifts->pluck('employee_profile_id')->unique()->values();
        $minimumStart = $shifts->min('starts_at');
        $maximumEnd = $shifts->max('ends_at');

        $sessions = EmployeeAttendanceSession::query()
            ->whereIn('employee_profile_id', $employeeIds)
            ->where('clock_in_at', '<', $maximumEnd)
            ->where(function ($query) use ($minimumStart) {
                $query->whereNull('clock_out_at')
                    ->orWhere('clock_out_at', '>', $minimumStart);
            })
            ->orderBy('clock_in_at')
            ->get()
            ->groupBy('employee_profile_id');

        return $shifts->mapWithKeys(function (EmployeeWorkShift $shift) use ($sessions) {
            $matching = $sessions->get($shift->employee_profile_id, collect())
                ->first(function (EmployeeAttendanceSession $session) use ($shift) {
                    $sessionEnd = $session->clock_out_at ?? now();

                    return $session->clock_in_at->lt($shift->ends_at)
                        && $sessionEnd->gt($shift->starts_at);
                });

            return [$shift->id => $matching];
        });
    }
}
