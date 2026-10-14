<?php

namespace App\Services\Workforce;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReportingHierarchyService
{
    public function __construct(private AdminActivityLogService $activityLog)
    {
    }

    /** Save a reporting change atomically and record the previous and new manager. */
    public function assignManager(EmployeeProfile $employee, ?int $managerId, User $actor): EmployeeProfile
    {
        return DB::transaction(function () use ($employee, $managerId, $actor) {
            $locked = EmployeeProfile::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $previousId = $locked->manager_employee_profile_id === null
                ? null : (int) $locked->manager_employee_profile_id;

            if ($previousId === $managerId) {
                return $locked;
            }

            if ($managerId !== null) {
                if ($managerId === (int) $locked->id) {
                    throw ValidationException::withMessages([
                        'manager_employee_profile_id' => __('An employee cannot manage themselves.'),
                    ]);
                }

                $manager = EmployeeProfile::query()->whereKey($managerId)->lockForUpdate()->first();
                if (!$manager || $manager->status !== EmployeeProfile::STATUS_ACTIVE
                    || !$manager->user || !$manager->user->hasPermission('workforce.manage')) {
                    throw ValidationException::withMessages([
                        'manager_employee_profile_id' => __('Choose an active manager who has Workforce management access.'),
                    ]);
                }

                // Validate the entire chain before saving; defensive traversal
                // also rejects any pre-existing corrupted/cyclic chain.
                $seen = [];
                $cursor = $manager;
                while ($cursor) {
                    if (isset($seen[$cursor->id]) || (int) $cursor->id === (int) $locked->id) {
                        throw ValidationException::withMessages([
                            'manager_employee_profile_id' => __('This reporting manager would create a management cycle.'),
                        ]);
                    }
                    $seen[$cursor->id] = true;
                    if (count($seen) > 1000) {
                        throw ValidationException::withMessages([
                            'manager_employee_profile_id' => __('Reporting chain is too deep to validate safely.'),
                        ]);
                    }
                    $cursor = $cursor->manager_employee_profile_id
                        ? EmployeeProfile::query()->whereKey($cursor->manager_employee_profile_id)->lockForUpdate()->first()
                        : null;
                }
            }

            $locked->manager_employee_profile_id = $managerId;
            $locked->save();

            $this->activityLog->log(
                'workforce',
                'employee_reporting_manager_changed',
                __('Employee reporting manager changed.'),
                $actor->id,
                $locked,
                ['previous_manager_employee_profile_id' => $previousId, 'new_manager_employee_profile_id' => $managerId]
            );

            return $locked->fresh();
        }, 3);
    }

    /** Read-only hierarchy traversal for future scoped policies. */
    public function reportsTo(EmployeeProfile $employee, EmployeeProfile $manager): bool
    {
        $seen = [];
        $cursorId = $employee->manager_employee_profile_id;

        while ($cursorId !== null && !isset($seen[$cursorId])) {
            if ((int) $cursorId === (int) $manager->id) {
                return true;
            }
            $seen[$cursorId] = true;
            $cursorId = EmployeeProfile::query()->whereKey($cursorId)->value('manager_employee_profile_id');
        }

        return false;
    }
}
