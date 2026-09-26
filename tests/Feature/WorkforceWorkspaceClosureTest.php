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
