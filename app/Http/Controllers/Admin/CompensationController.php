<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeProfile;
use App\Services\Workforce\CompensationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompensationController extends Controller
{
    public function __construct(protected CompensationService $compensationService)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $basis = (string) $request->string('pay_basis');

        $employees = EmployeeProfile::query()
            ->with(['user', 'compensation'])
            ->when($search, function ($query, $value) {
                $query->where(function ($inner) use ($value) {
                    $inner->where('employee_code', 'like', "%{$value}%")
                        ->orWhere('department', 'like', "%{$value}%")
                        ->orWhere('job_title', 'like', "%{$value}%")
                        ->orWhereHas('user', function ($userQuery) use ($value) {
                            $userQuery->where('name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%");
                        });
                });
            })
            ->when(array_key_exists($basis, EmployeeCompensation::payBasisOptions()), function ($query) use ($basis) {
                $query->whereHas('compensation', fn ($compensation) => $compensation->where('pay_basis', $basis));
            })
            ->orderBy('employee_code')
            ->paginate(25)
            ->withQueryString();

        return view('admin.workforce.payroll.compensation.index', compact('employees', 'search', 'basis'));
    }

    public function edit(EmployeeProfile $employeeProfile)
    {
        $employeeProfile->load(['user', 'compensation']);

        return view('admin.workforce.payroll.compensation.edit', [
            'employee' => $employeeProfile,
            'compensation' => $employeeProfile->compensation ?? new EmployeeCompensation([
                'employee_profile_id' => $employeeProfile->id,
                'pay_basis' => EmployeeCompensation::BASIS_SALARY,
                'effective_from' => now()->toDateString(),
                'overtime_eligible' => false,
            ]),
        ]);
    }

    public function update(Request $request, EmployeeProfile $employeeProfile)
    {
        $request->merge([
            'currency' => strtoupper(trim((string) $request->input('currency'))),
            'overtime_eligible' => $request->boolean('overtime_eligible'),
        ]);

        $data = $request->validate([
            'pay_basis' => ['required', Rule::in(array_keys(EmployeeCompensation::payBasisOptions()))],
            'base_rate' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'overtime_eligible' => ['required', 'boolean'],
            'overtime_rate_multiplier' => ['nullable', 'numeric', 'gt:0', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->compensationService->save($employeeProfile, $data, $request->user());

        return redirect()
            ->route('admin.workforce.payroll.compensation.index')
            ->with('success', __('Employee compensation saved.'));
    }
}
