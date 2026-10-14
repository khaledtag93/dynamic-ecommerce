<?php

namespace App\Services\Workforce;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A single reviewer-eligibility gate for leave review actions and their UI.
 *
 * Legacy scope routing stays the default. The optional direct_manager mode is
 * intentionally disabled until an organization expressly chooses it; it has
 * no implicit fallback, escalation, OOO delegate or auto-approval.
 */
final class LeaveApprovalRoutingService
{
    public const MODE_SCOPED = 'scoped';
    public const MODE_DIRECT_MANAGER = 'direct_manager';

    public function __construct(private WorkforceAccessService $access)
    {
    }

    public function mode(): string
    {
        return (string) config('workforce.leave_approval.routing_mode', self::MODE_SCOPED);
    }

    /**
     * Resolve eligible employees from a caller-scoped list (e.g. paginated UI).
     * A malformed mode fails closed. Never exposes employee details in output.
     *
     * @param iterable<EmployeeProfile> $employees
     * @return array<int, int>
     */
    public function reviewableEmployeeIds(User $actor, iterable $employees): array
    {
        if (! $actor->hasPermission('workforce.manage')) {
            return [];
        }

        $mode = $this->mode();
        if (! in_array($mode, [self::MODE_SCOPED, self::MODE_DIRECT_MANAGER], true)) {
            return [];
        }

        if ($mode === self::MODE_SCOPED) {
            // Reuse the existing organization-wide override and scoped descendants.
            $visible = array_fill_keys($this->access->visibleEmployeeIds($actor), true);
        } else {
            // Explicit opt-in: only the active direct manager, with manage
            // permission, may decide. No org-admin bypass or missing-manager
            // fallback is silently invented.
            $manager = $actor->employeeProfile()->first();
            if (! $manager || $manager->status !== EmployeeProfile::STATUS_ACTIVE) {
                return [];
            }
            $visible = null;
        }

        $ids = [];
        foreach ($employees as $employee) {
            if (! $employee instanceof EmployeeProfile || (int) $employee->user_id === (int) $actor->id) {
                continue;
            }
            if ($mode === self::MODE_SCOPED && ! isset($visible[$employee->id])) {
                continue;
            }
            if ($mode === self::MODE_DIRECT_MANAGER
                && (int) $employee->manager_employee_profile_id !== (int) $manager->id) {
                continue;
            }
            $ids[] = (int) $employee->id;
        }

        return array_values(array_unique($ids));
    }

    public function canReview(User $actor, EmployeeProfile|int $employee): bool
    {
        $target = $employee instanceof EmployeeProfile
            ? $employee
            : EmployeeProfile::query()->find($employee);

        return $target !== null
            && in_array((int) $target->id, $this->reviewableEmployeeIds($actor, [$target]), true);
    }

    public function assertReviewer(User $actor, EmployeeProfile|int $employee): void
    {
        abort_unless($this->canReview($actor, $employee), 403);
    }
}
