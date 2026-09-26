<?php

namespace Tests\Feature;

use Tests\TestCase;

class WorkforceWorkspaceClosureTest extends TestCase
{
    public function test_workforce_searches_are_bounded_and_escaped(): void
    {
        $controllers = [
            app_path('Http/Controllers/Admin/EmployeeController.php'),
            app_path('Http/Controllers/Admin/WorkShiftController.php'),
            app_path('Http/Controllers/Admin/AttendanceController.php'),
            app_path('Http/Controllers/Admin/LeaveController.php'),
            app_path('Http/Controllers/Admin/AttendanceCorrectionController.php'),
        ];

        foreach ($controllers as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString(
                'mb_substr(trim((string) $request->string(\'search\')), 0, 100)',
                $source
            );
            $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $source);
            $this->assertStringNotContainsString('"%{$search}%"', $source);
        }
    }

    public function test_workforce_action_forms_have_explicit_labels(): void
    {
        $views = [
            resource_path('views/admin/workforce/employees/_form.blade.php') => [
                'employeeUserId',
                'employeeCode',
                'employeeJobTitle',
                'employeeDepartment',
                'employeeEmploymentType',
                'employeeStatus',
                'employeePhone',
                'employeeHireDate',
                'employeeTerminationDate',
                'employeeNotes',
            ],
            resource_path('views/admin/workforce/schedule/_form.blade.php') => [
                'shiftEmployee',
                'shiftStartsAt',
                'shiftEndsAt',
                'shiftStatus',
                'shiftLocation',
                'shiftNotes',
            ],
            resource_path('views/admin/workforce/leave/my-leave.blade.php') => [
                'leaveYear',
                'leaveRequestType',
                'leaveStartsOn',
                'leaveEndsOn',
                'leaveReason',
            ],
            resource_path('views/admin/workforce/corrections/create.blade.php') => [
                'correctionClockIn',
                'correctionClockOut',
                'correctionReason',
            ],
            resource_path('views/admin/workforce/time-clock.blade.php') => [
                'breakNote',
                'clockOutNotes',
                'clockInNotes',
            ],
        ];

        foreach ($views as $path => $controlIds) {
            $source = file_get_contents($path);

            foreach ($controlIds as $controlId) {
                $this->assertStringContainsString('for="' . $controlId . '"', $source);
                $this->assertStringContainsString('id="' . $controlId . '"', $source);
            }
        }

        $employee = file_get_contents(resource_path('views/admin/workforce/employees/_form.blade.php'));
        $shift = file_get_contents(resource_path('views/admin/workforce/schedule/_form.blade.php'));
        $leave = file_get_contents(resource_path('views/admin/workforce/leave/my-leave.blade.php'));
        $correction = file_get_contents(resource_path('views/admin/workforce/corrections/create.blade.php'));

        foreach ([
            [$employee, 'employeeUserId'],
            [$employee, 'employeeCode'],
            [$employee, 'employeeEmploymentType'],
            [$employee, 'employeeStatus'],
            [$shift, 'shiftEmployee'],
            [$shift, 'shiftStartsAt'],
            [$shift, 'shiftEndsAt'],
            [$shift, 'shiftStatus'],
            [$leave, 'leaveRequestType'],
            [$leave, 'leaveStartsOn'],
            [$leave, 'leaveEndsOn'],
            [$correction, 'correctionClockIn'],
            [$correction, 'correctionClockOut'],
            [$correction, 'correctionReason'],
        ] as [$source, $controlId]) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($controlId, '/') . '"[^>]*aria-required="true"/',
                $source
            );
        }
    }

    public function test_workforce_financial_controls_and_compensation_search_are_hardened(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/CompensationController.php'));

        $this->assertStringContainsString(
            'mb_substr(trim((string) $request->string(\'search\')), 0, 100)',
            $controller
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$value}%"', $controller);

        $views = [
            resource_path('views/admin/workforce/payroll/compensation/index.blade.php') => [
                'compensationSearch',
                'compensationBasisFilter',
            ],
            resource_path('views/admin/workforce/payroll/compensation/edit.blade.php') => [
                'compensationPayBasis',
                'compensationBaseRate',
                'compensationCurrency',
                'compensationEffectiveFrom',
                'compensationEffectiveTo',
                'overtimeEligible',
                'compensationOvertimeMultiplier',
                'compensationNotes',
            ],
            resource_path('views/admin/workforce/payroll/index.blade.php') => [
                'payrollPeriodName',
                'payrollPeriodStartsOn',
                'payrollPeriodEndsOn',
                'payrollPeriodPayDate',
                'payrollPeriodNotes',
            ],
            resource_path('views/admin/workforce/payroll/run.blade.php') => [
                'payrollAdjustmentType-{{ $entry->id }}',
                'payrollAdjustmentLabel-{{ $entry->id }}',
                'payrollAdjustmentAmount-{{ $entry->id }}',
                'payrollAdjustmentQuantity-{{ $entry->id }}',
                'payrollAdjustmentRate-{{ $entry->id }}',
                'payrollAdjustmentReason-{{ $entry->id }}',
            ],
            resource_path('views/admin/workforce/leave/adjustment.blade.php') => [
                'leaveAdjustmentEmployee',
                'leaveAdjustmentType',
                'leaveAdjustmentYear',
                'leaveAdjustmentKind',
                'leaveAdjustmentDays',
                'leaveAdjustmentReason',
            ],
        ];

        foreach ($views as $path => $controlIds) {
            $source = file_get_contents($path);

            foreach ($controlIds as $controlId) {
                $this->assertStringContainsString('for="' . $controlId . '"', $source);
                $this->assertStringContainsString('id="' . $controlId . '"', $source);
            }
        }

        foreach ([
            [resource_path('views/admin/workforce/payroll/compensation/edit.blade.php'), 'compensationPayBasis'],
            [resource_path('views/admin/workforce/payroll/compensation/edit.blade.php'), 'compensationBaseRate'],
            [resource_path('views/admin/workforce/payroll/compensation/edit.blade.php'), 'compensationCurrency'],
            [resource_path('views/admin/workforce/payroll/compensation/edit.blade.php'), 'compensationEffectiveFrom'],
            [resource_path('views/admin/workforce/payroll/index.blade.php'), 'payrollPeriodName'],
            [resource_path('views/admin/workforce/payroll/index.blade.php'), 'payrollPeriodStartsOn'],
            [resource_path('views/admin/workforce/payroll/index.blade.php'), 'payrollPeriodEndsOn'],
            [resource_path('views/admin/workforce/leave/adjustment.blade.php'), 'leaveAdjustmentEmployee'],
            [resource_path('views/admin/workforce/leave/adjustment.blade.php'), 'leaveAdjustmentType'],
            [resource_path('views/admin/workforce/leave/adjustment.blade.php'), 'leaveAdjustmentYear'],
            [resource_path('views/admin/workforce/leave/adjustment.blade.php'), 'leaveAdjustmentKind'],
            [resource_path('views/admin/workforce/leave/adjustment.blade.php'), 'leaveAdjustmentDays'],
            [resource_path('views/admin/workforce/leave/adjustment.blade.php'), 'leaveAdjustmentReason'],
        ] as [$path, $controlId]) {
            $source = file_get_contents($path);
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($controlId, '/') . '"[^>]*aria-required="true"/',
                $source
            );
        }
    }

    public function test_leave_type_editor_controls_have_explicit_labels_and_required_semantics(): void
    {
        $view = file_get_contents(resource_path('views/admin/workforce/leave-types/_form.blade.php'));

        foreach ([
            'leaveTypeCode',
            'leaveTypeName',
            'leaveTypeNameAr',
            'leaveTypeEntitlement',
            'leaveTypePaid',
            'leaveTypeActive',
            'leaveTypeNotes',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        foreach ([
            'leaveTypeCode',
            'leaveTypeName',
            'leaveTypeEntitlement',
        ] as $controlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($controlId, '/') . '"[^>]*aria-required="true"/',
                $view
            );
        }
    }

    public function test_workforce_list_filters_have_explicit_labels(): void
    {
        $views = [
            resource_path('views/admin/workforce/employees/index.blade.php') => [
                'workforceEmployeeSearch',
                'workforceEmployeeStatus',
                'workforceEmployeeType',
                'workforceEmployeeDepartment',
                'workforceEmployeePerPage',
            ],
            resource_path('views/admin/workforce/schedule/index.blade.php') => [
                'workforceScheduleSearch',
                'workforceScheduleStatus',
                'workforceScheduleDepartment',
                'workforceScheduleFrom',
                'workforceScheduleTo',
                'workforceSchedulePerPage',
            ],
            resource_path('views/admin/workforce/attendance/index.blade.php') => [
                'workforceAttendanceSearch',
                'workforceAttendanceStatus',
                'workforceAttendanceDate',
                'workforceAttendancePerPage',
            ],
            resource_path('views/admin/workforce/leave/index.blade.php') => [
                'workforceLeaveSearch',
                'workforceLeaveStatus',
                'workforceLeaveType',
                'workforceLeavePerPage',
            ],
            resource_path('views/admin/workforce/corrections/index.blade.php') => [
                'workforceCorrectionSearch',
                'workforceCorrectionStatus',
                'workforceCorrectionPerPage',
            ],
        ];

        foreach ($views as $path => $controlIds) {
            $source = file_get_contents($path);

            foreach ($controlIds as $controlId) {
                $this->assertStringContainsString('for="' . $controlId . '"', $source);
                $this->assertStringContainsString('id="' . $controlId . '"', $source);
            }
        }
    }
}
