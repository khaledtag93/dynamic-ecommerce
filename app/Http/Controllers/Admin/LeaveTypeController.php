<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveType;
use App\Services\Commerce\AdminActivityLogService;
use App\Services\Workforce\WorkforceAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveTypeController extends Controller
{
    public function __construct(protected AdminActivityLogService $activityLogService, protected WorkforceAccessService $access)
    {
    }

    public function index()
    {
        $this->access->assertOrgAdmin(request()->user());
        $types = EmployeeLeaveType::query()
            ->withCount(['requests', 'adjustments'])
            ->orderBy('name')
            ->get();

        return view('admin.workforce.leave-types.index', compact('types'));
    }

    public function create()
    {
        $this->access->assertOrgAdmin(request()->user());
        return view('admin.workforce.leave-types.create', [
            'leaveType' => new EmployeeLeaveType(),
        ]);
    }

    public function store(Request $request)
    {
        $this->access->assertOrgAdmin($request->user());
        $data = $this->validated($request);

        $leaveType = EmployeeLeaveType::query()->create($data);

        $this->activityLogService->log(
            'workforce',
            'leave_type_created',
            __('Leave type created.'),
            $request->user()?->id,
            $leaveType,
            ['code' => $leaveType->code]
        );

        return redirect()
            ->route('admin.workforce.leave-types.index')
            ->with('success', __('Leave type created.'));
    }

    public function edit(EmployeeLeaveType $employeeLeaveType)
    {
        $this->access->assertOrgAdmin(request()->user());
        return view('admin.workforce.leave-types.edit', [
            'leaveType' => $employeeLeaveType,
        ]);
    }

    public function update(Request $request, EmployeeLeaveType $employeeLeaveType)
    {
        $this->access->assertOrgAdmin($request->user());
        $data = $this->validated($request, $employeeLeaveType);

        $employeeLeaveType->update($data);

        $this->activityLogService->log(
            'workforce',
            'leave_type_updated',
            __('Leave type updated.'),
            $request->user()?->id,
            $employeeLeaveType,
            ['code' => $employeeLeaveType->code]
        );

        return redirect()
            ->route('admin.workforce.leave-types.index')
            ->with('success', __('Leave type updated.'));
    }

    private function validated(Request $request, ?EmployeeLeaveType $leaveType = null): array
    {
        $holidayLines = preg_split('/\r\n|\r|\n/', (string) $request->input('holiday_dates_input', ''));
        $request->merge([
            'holiday_dates' => array_values(array_filter(array_map('trim', $holidayLines), fn ($date) => $date !== '')),
            'code' => Str::upper(trim((string) $request->input('code'))),
            'is_paid' => $request->boolean('is_paid'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('employee_leave_types', 'code')->ignore($leaveType?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'is_paid' => ['required', 'boolean'],
            'default_annual_entitlement_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'duration_mode' => ['sometimes', Rule::in(['legacy', 'fixed', 'flexible'])],
            'fixed_duration_days' => ['nullable', 'integer', 'between:1,365'],
            'min_request_days' => ['nullable', 'integer', 'between:1,365'],
            'max_request_days' => ['nullable', 'integer', 'between:1,365'],
            'counting_mode' => ['sometimes', Rule::in(['calendar', 'workdays'])],
            'working_weekdays' => ['nullable', 'array', 'max:7'],
            'working_weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'holiday_dates' => ['nullable', 'array', 'max:366'],
            'holiday_dates.*' => ['date_format:Y-m-d', 'distinct'],
        ]);

        $mode = $data['duration_mode'] ?? ($leaveType?->duration_mode ?? 'legacy');
        $counting = $mode === 'legacy' ? 'calendar' : ($data['counting_mode'] ?? ($leaveType?->counting_mode ?? 'calendar'));
        if ($mode === 'fixed' && empty($data['fixed_duration_days'])) {
            throw ValidationException::withMessages(['fixed_duration_days' => __('Set the fixed leave duration in days.')]);
        }
        if ($mode === 'flexible' && (empty($data['min_request_days']) || empty($data['max_request_days']) ||
            (int) $data['min_request_days'] > (int) $data['max_request_days'])) {
            throw ValidationException::withMessages(['max_request_days' => __('Set a valid minimum and maximum leave duration.')]);
        }
        if ($counting === 'workdays' && empty($data['working_weekdays'])) {
            throw ValidationException::withMessages(['working_weekdays' => __('Select the working weekdays explicitly.')]);
        }

        $data['duration_mode'] = $mode;
        $data['counting_mode'] = $counting;
        $data['fixed_duration_days'] = $mode === 'fixed' ? (int) $data['fixed_duration_days'] : null;
        $data['min_request_days'] = $mode === 'flexible' ? (int) $data['min_request_days'] : null;
        $data['max_request_days'] = $mode === 'flexible' ? (int) $data['max_request_days'] : null;
        $data['working_weekdays'] = $counting === 'workdays' ? array_map('intval', $data['working_weekdays']) : null;
        $data['holiday_dates'] = $counting === 'workdays' ? array_values($data['holiday_dates'] ?? []) : null;

        return $data;
    }
}
