<?php

namespace App\Services\Workforce;

use App\Models\EmployeeLeaveAdjustment;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;

class LeaveBalanceService
{
    public function balance(EmployeeProfile $employee, EmployeeLeaveType $type, int $year): array
    {
        $entitlement = (float) $type->default_annual_entitlement_days;

        $adjustments = (float) EmployeeLeaveAdjustment::query()
            ->where('employee_profile_id', $employee->id)
            ->where('employee_leave_type_id', $type->id)
            ->where('year', $year)
            ->sum('days');

        $used = (float) EmployeeLeaveRequest::query()
            ->where('employee_profile_id', $employee->id)
            ->where('employee_leave_type_id', $type->id)
            ->where('status', EmployeeLeaveRequest::STATUS_APPROVED)
            ->whereYear('starts_on', $year)
            ->sum('requested_days');

        $pending = (float) EmployeeLeaveRequest::query()
            ->where('employee_profile_id', $employee->id)
            ->where('employee_leave_type_id', $type->id)
            ->where('status', EmployeeLeaveRequest::STATUS_PENDING)
            ->whereYear('starts_on', $year)
            ->sum('requested_days');

        $available = $entitlement + $adjustments - $used;

        return [
            'year' => $year,
            'entitlement' => round($entitlement, 2),
            'adjustments' => round($adjustments, 2),
            'used' => round($used, 2),
            'pending' => round($pending, 2),
            'available' => round($available, 2),
            'projected_available' => round($available - $pending, 2),
        ];
    }

    public function balancesForEmployee(EmployeeProfile $employee, int $year)
    {
        return EmployeeLeaveType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (EmployeeLeaveType $type) => [
                'type' => $type,
                'balance' => $this->balance($employee, $type, $year),
            ]);
    }
}
