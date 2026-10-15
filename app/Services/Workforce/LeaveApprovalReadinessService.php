<?php

namespace App\Services\Workforce;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read-only, request-time readiness for the ALREADY scoped, paginated list.
 * Never assigns a reviewer, escalates, or exposes reviewer identities.
 */
final class LeaveApprovalReadinessService
{
    public const MINE = 'mine';
    public const OTHER = 'other';
    public const UNASSIGNED = 'unassigned';
    public const CONFIGURATION = 'configuration';

    public function __construct(private LeaveApprovalRoutingService $routing)
    {
    }

    /**
     * @param Collection<int, EmployeeLeaveRequest> $requests A caller-authorized page.
     * @param array<int, int> $myReviewableEmployeeIds From the same routing gate.
     * @return array{states: array<int, string>, counts: array<string, int>}
     */
    public function forPage(User $actor, Collection $requests, array $myReviewableEmployeeIds): array
    {
        $mode = $this->routing->mode();
        $allowedMode = in_array($mode, [
            LeaveApprovalRoutingService::MODE_SCOPED,
            LeaveApprovalRoutingService::MODE_DIRECT_MANAGER,
        ], true);
        $myIds = array_fill_keys($myReviewableEmployeeIds, true);
        $globalReviewers = $mode === LeaveApprovalRoutingService::MODE_SCOPED
            ? $this->organizationWideReviewers()
            : collect();

        $states = [];
        $counts = [
            self::MINE => 0,
            self::OTHER => 0,
            self::UNASSIGNED => 0,
            self::CONFIGURATION => 0,
        ];

        foreach ($requests as $leave) {
            if (! $leave->isPending()) {
                continue;
            }

            $target = $leave->employee;
            if (! $allowedMode) {
                $state = self::CONFIGURATION;
            } elseif ($target && isset($myIds[$target->id])) {
                $state = self::MINE;
            } elseif (! $target) {
                // Missing or inconsistent employee references need admin review,
                // not an unsafe claim that a reviewer cannot exist.
                $state = self::CONFIGURATION;
            } else {
                $otherExists = $this->otherReviewerExists($target, $mode, $globalReviewers);
                $state = $otherExists === null ? self::CONFIGURATION
                    : ($otherExists ? self::OTHER : self::UNASSIGNED);
            }

            $states[(int) $leave->id] = $state;
            $counts[$state]++;
        }

        return ['states' => $states, 'counts' => $counts];
    }

    /**
     * Only admin-role candidates with explicit global Workforce scope can
     * possibly review everyone; use the central routing gate for final proof.
     */
    private function organizationWideReviewers(): Collection
    {
        return User::query()
            ->where('role_as', 1)
            ->whereHas('roles', fn ($role) => $role
                ->where('slug', 'super_admin')
                ->orWhereHas('permissions', fn ($permission) => $permission
                    ->where('slug', 'workforce.scope.all')))
            ->get()
            ->filter(fn (User $candidate) => $candidate->hasPermission('workforce.manage')
                && $candidate->hasPermission('workforce.scope.all'));
    }

    private function otherReviewerExists(EmployeeProfile $target, string $mode, Collection $globalReviewers): ?bool
    {
        if ($mode === LeaveApprovalRoutingService::MODE_DIRECT_MANAGER) {
            $manager = $target->manager_employee_profile_id
                ? EmployeeProfile::query()->with('user')->find($target->manager_employee_profile_id)
                : null;

            return $manager?->user !== null
                && $this->routing->canReview($manager->user, $target);
        }

        foreach ($globalReviewers as $candidate) {
            // Global scope + manage is precisely the scoped-mode authorization,
            // except for an employee reviewing their own request.
            if ((int) $candidate->id !== (int) $target->user_id) {
                return true;
            }
        }

        // The remaining possible reviewers are reporting-chain ancestors.
        // A legacy cycle or unexpectedly deep tree must not produce a false
        // "unassigned" label: the review action still stays fail-closed.
        $seen = [];
        $cursor = $target->manager_employee_profile_id;
        while ($cursor !== null) {
            if (isset($seen[$cursor]) || count($seen) >= 1000) {
                return null; // uncertain hierarchy, not a proven reviewer
            }
            $seen[$cursor] = true;
            $manager = EmployeeProfile::query()->with('user')->find($cursor);
            if (! $manager) {
                break;
            }
            if ($manager->user
                && (int) $manager->user_id !== (int) $target->user_id
                && $this->routing->canReview($manager->user, $target)) {
                return true;
            }
            $cursor = $manager->manager_employee_profile_id;
        }

        return false;
    }
}
