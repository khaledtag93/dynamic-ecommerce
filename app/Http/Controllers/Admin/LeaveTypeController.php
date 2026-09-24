<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $types = EmployeeLeaveType::query()
            ->withCount(['requests', 'adjustments'])
            ->orderBy('name')
            ->get();

        return view('admin.workforce.leave-types.index', compact('types'));
    }

    public function create()
    {
        return view('admin.workforce.leave-types.create', [
            'leaveType' => new EmployeeLeaveType(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        EmployeeLeaveType::query()->create($data);

        return redirect()
            ->route('admin.workforce.leave-types.index')
            ->with('success', __('Leave type created.'));
    }

    public function edit(EmployeeLeaveType $employeeLeaveType)
    {
        return view('admin.workforce.leave-types.edit', [
            'leaveType' => $employeeLeaveType,
        ]);
    }

    public function update(Request $request, EmployeeLeaveType $employeeLeaveType)
    {
        $data = $this->validated($request, $employeeLeaveType);

        $employeeLeaveType->update($data);

        return redirect()
            ->route('admin.workforce.leave-types.index')
            ->with('success', __('Leave type updated.'));
    }

    private function validated(Request $request, ?EmployeeLeaveType $leaveType = null): array
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
            'is_paid' => $request->boolean('is_paid'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return $request->validate([
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
        ]);
    }
}
