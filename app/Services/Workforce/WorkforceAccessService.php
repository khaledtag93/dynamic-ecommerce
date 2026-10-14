<?php

namespace App\Services\Workforce;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class WorkforceAccessService
{
    /** Organization-wide access is explicit and independent of Workforce page access. */
    public function isOrganizationAdmin(User $actor): bool
    {
        return $actor->hasPermission('workforce.scope.all');
    }

    /**
     * Visible staff are self and all descendants, including indirect reports.
     * Uses UNION to stop traversing if a legacy cycle somehow exists.
     */
    public function visibleEmployeeIds(User $actor): array
    {
        if ($this->isOrganizationAdmin($actor)) {
            return EmployeeProfile::query()->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        return array_map(
            fn ($row) => (int) $row->id,
            DB::select(
                'WITH RECURSIVE report_tree (id) AS (
                    SELECT id FROM employee_profiles WHERE user_id = ?
                    UNION
                    SELECT ep.id FROM employee_profiles ep
                    INNER JOIN report_tree rt ON ep.manager_employee_profile_id = rt.id
                ) SELECT id FROM report_tree',
                [$actor->id]
            )
        );
    }

    public function restrictEmployees(Builder $query, User $actor): Builder
    {
        return $this->isOrganizationAdmin($actor)
            ? $query
            : $query->whereIn('id', $this->visibleEmployeeIds($actor));
    }

    public function restrictForeignKey(Builder $query, User $actor, string $column = 'employee_profile_id'): Builder
    {
        return $this->isOrganizationAdmin($actor)
            ? $query
            : $query->whereIn($column, $this->visibleEmployeeIds($actor));
    }

    public function canView(User $actor, EmployeeProfile|int $employee): bool
    {
        if ($this->isOrganizationAdmin($actor)) {
            return true;
        }

        $id = $employee instanceof EmployeeProfile ? (int) $employee->id : $employee;

        return in_array($id, $this->visibleEmployeeIds($actor), true);
    }

    public function canManage(User $actor, EmployeeProfile|int $employee): bool
    {
        if (!$actor->hasPermission('workforce.manage')) {
            return false;
        }

        $target = $employee instanceof EmployeeProfile
            ? $employee
            : EmployeeProfile::query()->find($employee);

        if (!$target) {
            return false;
        }

        // Team managers can act on reports, never on their own employment data.
        if (!$this->isOrganizationAdmin($actor) && (int) $target->user_id === (int) $actor->id) {
            return false;
        }

        return $this->canView($actor, $target);
    }

    public function assertView(User $actor, EmployeeProfile|int $employee): void
    {
        abort_unless($this->canView($actor, $employee), 403);
    }

    public function assertManage(User $actor, EmployeeProfile|int $employee): void
    {
        abort_unless($this->canManage($actor, $employee), 403);
    }

    public function assertOrgAdmin(User $actor): void
    {
        abort_unless($this->isOrganizationAdmin($actor), 403);
    }

    /** A reviewer cannot approve or reject their own leave/correction, even as org admin. */
    public function assertReviewer(User $actor, EmployeeProfile|int $employee): void
    {
        $target = $employee instanceof EmployeeProfile
            ? $employee
            : EmployeeProfile::query()->findOrFail($employee);

        abort_if((int) $target->user_id === (int) $actor->id, 403);
        $this->assertManage($actor, $target);
    }
}
