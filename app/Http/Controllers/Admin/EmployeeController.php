<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->string('status');
        $employmentType = (string) $request->string('employment_type');

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => array_key_exists($status, EmployeeProfile::statusOptions()) ? $status : '',
            'employment_type' => array_key_exists($employmentType, EmployeeProfile::employmentTypeOptions()) ? $employmentType : '',
            'department' => trim((string) $request->string('department')),
            'per_page' => max(15, min(100, (int) $request->integer('per_page', 15))),
        ];

        $employees = EmployeeProfile::query()
            ->with(['user.roles', 'openAttendanceSession'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'], fn ($query, $value) => $query->where('status', $value))
            ->when($filters['employment_type'], fn ($query, $value) => $query->where('employment_type', $value))
            ->when($filters['department'], fn ($query, $value) => $query->where('department', $value))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'on_leave' THEN 1 ELSE 2 END")
            ->orderBy('employee_code')
            ->paginate($filters['per_page'])
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.workforce.employees._results', compact('employees', 'filters'));
        }

        $stats = [
            'total' => EmployeeProfile::count(),
            'active' => EmployeeProfile::where('status', EmployeeProfile::STATUS_ACTIVE)->count(),
            'on_leave' => EmployeeProfile::where('status', EmployeeProfile::STATUS_ON_LEAVE)->count(),
            'clocked_in' => EmployeeProfile::whereHas('openAttendanceSession')->count(),
        ];

        $departments = EmployeeProfile::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return view('admin.workforce.employees.index', compact(
            'employees',
            'filters',
            'stats',
            'departments',
        ));
    }

    public function create()
    {
        $candidateUsers = User::query()
            ->where('role_as', 1)
            ->whereDoesntHave('employeeProfile')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.workforce.employees.create', [
            'employee' => new EmployeeProfile(),
            'candidateUsers' => $candidateUsers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);

        $employee = EmployeeProfile::query()->create($data);

        return redirect()
            ->route('admin.workforce.employees.edit', $employee)
            ->with('success', __('Employee profile created successfully.'));
    }

    public function edit(EmployeeProfile $employeeProfile)
    {
        $employeeProfile->load(['user.roles', 'openAttendanceSession']);

        return view('admin.workforce.employees.edit', [
            'employee' => $employeeProfile,
        ]);
    }

    public function update(Request $request, EmployeeProfile $employeeProfile)
    {
        $data = $this->validateEmployee($request, $employeeProfile);
        unset($data['user_id']);

        if (($data['status'] ?? EmployeeProfile::STATUS_ACTIVE) !== EmployeeProfile::STATUS_ACTIVE
            && $employeeProfile->openAttendanceSession()->exists()) {
            throw ValidationException::withMessages([
                'status' => __('Clock out the employee before changing to a non-active employment status.'),
            ]);
        }

        $employeeProfile->update($data);

        return redirect()
            ->route('admin.workforce.employees.edit', $employeeProfile)
            ->with('success', __('Employee profile updated successfully.'));
    }

    private function validateEmployee(Request $request, ?EmployeeProfile $employee = null): array
    {
        $request->merge([
            'employee_code' => Str::upper(trim((string) $request->input('employee_code'))),
        ]);

        $rules = [
            'employee_code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('employee_profiles', 'employee_code')->ignore($employee?->id),
            ],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'employment_type' => ['required', Rule::in(array_keys(EmployeeProfile::employmentTypeOptions()))],
            'status' => ['required', Rule::in(array_keys(EmployeeProfile::statusOptions()))],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $employee) {
            $rules['user_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role_as', 1)),
                Rule::unique('employee_profiles', 'user_id'),
            ];
        }

        $data = $request->validate($rules);

        if (($data['status'] ?? null) === EmployeeProfile::STATUS_TERMINATED) {
            $data['termination_date'] = $data['termination_date'] ?? now()->toDateString();
        } else {
            $data['termination_date'] = null;
        }

        return $data;
    }
}
