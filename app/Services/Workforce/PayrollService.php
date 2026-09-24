<?php

namespace App\Services\Workforce;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeProfile;
use App\Models\PayrollAdjustment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function createPeriod(array $data, User $actor): PayrollPeriod
    {
        return DB::transaction(function () use ($data, $actor) {
            $start = Carbon::parse($data['starts_on'])->startOfDay();
            $end = Carbon::parse($data['ends_on'])->startOfDay();

            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'ends_on' => __('Payroll period end date must be on or after the start date.'),
                ]);
            }

            $overlap = PayrollPeriod::query()
                ->where('starts_on', '<=', $end->toDateString())
                ->where('ends_on', '>=', $start->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'starts_on' => __('This payroll period overlaps an existing payroll period.'),
                ]);
            }

            $period = PayrollPeriod::query()->create([
                'name' => trim((string) $data['name']),
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'pay_date' => $data['pay_date'] ?? null,
                'status' => PayrollPeriod::STATUS_OPEN,
                'notes' => $this->nullableTrim($data['notes'] ?? null),
            ]);

            $this->activityLogService->log(
                'workforce',
                'payroll_period_created',
                __('Payroll period created.'),
                $actor->id,
                $period,
                [
                    'starts_on' => $period->starts_on->toDateString(),
                    'ends_on' => $period->ends_on->toDateString(),
                ]
            );

            return $period;
        });
    }

    public function generateRun(PayrollPeriod $period, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($period, $actor) {
            $lockedPeriod = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if (! $lockedPeriod->isOpen()) {
                throw ValidationException::withMessages([
                    'payroll' => __('Only open payroll periods can generate a payroll run.'),
                ]);
            }

            if ($lockedPeriod->payrollRun()->exists()) {
                throw ValidationException::withMessages([
                    'payroll' => __('This payroll period already has a payroll run.'),
                ]);
            }

            $this->guardStableInputs($lockedPeriod);

            $run = PayrollRun::query()->create([
                'payroll_period_id' => $lockedPeriod->id,
                'status' => PayrollRun::STATUS_DRAFT,
                'created_by_user_id' => $actor->id,
                'notes' => null,
            ]);

            $compensations = EmployeeCompensation::query()
                ->with('employee.user')
                ->where('effective_from', '<=', $lockedPeriod->ends_on)
                ->where(function ($query) use ($lockedPeriod) {
                    $query->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $lockedPeriod->starts_on);
                })
                ->whereHas('employee', function ($query) use ($lockedPeriod) {
                    $query->where(function ($hire) use ($lockedPeriod) {
                        $hire->whereNull('hire_date')
                            ->orWhere('hire_date', '<=', $lockedPeriod->ends_on);
                    })->where(function ($termination) use ($lockedPeriod) {
                        $termination->whereNull('termination_date')
                            ->orWhere('termination_date', '>=', $lockedPeriod->starts_on);
                    });
                })
                ->orderBy('employee_profile_id')
                ->get();

            foreach ($compensations as $compensation) {
                $this->createEntrySnapshot($run, $lockedPeriod, $compensation);
            }

            if ($run->entries()->count() === 0) {
                throw ValidationException::withMessages([
                    'payroll' => __('No employees with effective compensation were found for this payroll period.'),
                ]);
            }

            $this->activityLogService->log(
                'workforce',
                'payroll_run_generated',
                __('Payroll run generated.'),
                $actor->id,
                $run,
                [
                    'payroll_period_id' => $lockedPeriod->id,
                    'entry_count' => $run->entries()->count(),
                ]
            );

            return $run->fresh();
        });
    }

    public function addAdjustment(PayrollEntry $entry, array $data, User $actor): PayrollAdjustment
    {
        return DB::transaction(function () use ($entry, $data, $actor) {
            $run = PayrollRun::query()->whereKey($entry->payroll_run_id)->lockForUpdate()->firstOrFail();

            if (! $run->isDraft()) {
                throw ValidationException::withMessages([
                    'payroll' => __('Payroll adjustments can only be changed while the run is Draft.'),
                ]);
            }

            $lockedEntry = PayrollEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            $type = (string) $data['type'];
            if ($type === PayrollAdjustment::TYPE_OVERTIME
                && ! (bool) data_get($lockedEntry->calculation_snapshot, 'compensation.overtime_eligible', false)) {
                throw ValidationException::withMessages([
                    'type' => __('This employee is not marked as overtime eligible in the payroll snapshot.'),
                ]);
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Payroll adjustment amount must be greater than zero.'),
                ]);
            }

            $adjustment = PayrollAdjustment::query()->create([
                'payroll_entry_id' => $lockedEntry->id,
                'type' => $type,
                'label' => trim((string) $data['label']),
                'amount' => $amount,
                'quantity' => $data['quantity'] ?? null,
                'rate' => $data['rate'] ?? null,
                'reason' => trim((string) $data['reason']),
                'created_by_user_id' => $actor->id,
            ]);

            $this->recalculateEntry($lockedEntry);

            $this->activityLogService->log(
                'workforce',
                'payroll_adjustment_added',
                __('Payroll adjustment added.'),
                $actor->id,
                $adjustment,
                [
                    'payroll_entry_id' => $lockedEntry->id,
                    'type' => $type,
                    'amount' => $amount,
                ]
            );

            return $adjustment->fresh();
        });
    }

    public function removeAdjustment(PayrollAdjustment $adjustment, User $actor): void
    {
        DB::transaction(function () use ($adjustment, $actor) {
            $entry = PayrollEntry::query()->whereKey($adjustment->payroll_entry_id)->lockForUpdate()->firstOrFail();
            $run = PayrollRun::query()->whereKey($entry->payroll_run_id)->lockForUpdate()->firstOrFail();

            if (! $run->isDraft()) {
                throw ValidationException::withMessages([
                    'payroll' => __('Payroll adjustments can only be changed while the run is Draft.'),
                ]);
            }

            $lockedAdjustment = PayrollAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            $meta = [
                'payroll_entry_id' => $entry->id,
                'type' => $lockedAdjustment->type,
                'amount' => (float) $lockedAdjustment->amount,
            ];

            $lockedAdjustment->delete();
            $this->recalculateEntry($entry);

            $this->activityLogService->log(
                'workforce',
                'payroll_adjustment_removed',
                __('Payroll adjustment removed.'),
                $actor->id,
                null,
                $meta
            );
        });
    }

    public function approveRun(PayrollRun $run, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor) {
            $lockedRun = PayrollRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();

            if (! $lockedRun->isDraft()) {
                throw ValidationException::withMessages([
                    'payroll' => __('Only Draft payroll runs can be approved.'),
                ]);
            }

            $period = PayrollPeriod::query()->whereKey($lockedRun->payroll_period_id)->lockForUpdate()->firstOrFail();

            foreach ($lockedRun->entries()->lockForUpdate()->get() as $entry) {
                $this->recalculateEntry($entry);
            }

            $lockedRun->update([
                'status' => PayrollRun::STATUS_APPROVED,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
            ]);

            $period->update(['status' => PayrollPeriod::STATUS_CLOSED]);

            $this->activityLogService->log(
                'workforce',
                'payroll_run_approved',
                __('Payroll run approved.'),
                $actor->id,
                $lockedRun,
                ['payroll_period_id' => $period->id]
            );

            return $lockedRun->fresh();
        });
    }

    public function markPaid(PayrollRun $run, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor) {
            $lockedRun = PayrollRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();

            if (! $lockedRun->isApproved()) {
                throw ValidationException::withMessages([
                    'payroll' => __('Only Approved payroll runs can be marked Paid.'),
                ]);
            }

            $lockedRun->update([
                'status' => PayrollRun::STATUS_PAID,
                'paid_by_user_id' => $actor->id,
                'paid_at' => now(),
            ]);

            $this->activityLogService->log(
                'workforce',
                'payroll_run_paid',
                __('Payroll run marked Paid.'),
                $actor->id,
                $lockedRun,
                ['payroll_period_id' => $lockedRun->payroll_period_id]
            );

            return $lockedRun->fresh();
        });
    }

    private function guardStableInputs(PayrollPeriod $period): void
    {
        $start = $period->starts_on->copy()->startOfDay();
        $endExclusive = $period->ends_on->copy()->addDay()->startOfDay();

        $openAttendance = EmployeeAttendanceSession::query()
            ->whereNull('clock_out_at')
            ->where('clock_in_at', '<', $endExclusive)
            ->exists();

        if ($openAttendance) {
            throw ValidationException::withMessages([
                'payroll' => __('Close all attendance sessions touching this payroll period before generating payroll.'),
            ]);
        }

        $pendingCorrection = EmployeeAttendanceSession::query()
            ->where('clock_in_at', '<', $endExclusive)
            ->where('clock_out_at', '>=', $start)
            ->whereHas('pendingCorrection')
            ->exists();

        if ($pendingCorrection) {
            throw ValidationException::withMessages([
                'payroll' => __('Resolve pending attendance corrections touching this payroll period before generating payroll.'),
            ]);
        }

        $pendingLeave = EmployeeLeaveRequest::query()
            ->where('status', EmployeeLeaveRequest::STATUS_PENDING)
            ->where('starts_on', '<=', $period->ends_on)
            ->where('ends_on', '>=', $period->starts_on)
            ->exists();

        if ($pendingLeave) {
            throw ValidationException::withMessages([
                'payroll' => __('Resolve pending leave requests touching this payroll period before generating payroll.'),
            ]);
        }
    }

    private function createEntrySnapshot(
        PayrollRun $run,
        PayrollPeriod $period,
        EmployeeCompensation $compensation,
    ): PayrollEntry {
        $employee = $compensation->employee;
        $start = $period->starts_on->copy()->startOfDay();
        $endExclusive = $period->ends_on->copy()->addDay()->startOfDay();

        $sessions = EmployeeAttendanceSession::query()
            ->with(['breaks', 'approvedCorrection'])
            ->where('employee_profile_id', $employee->id)
            ->where('clock_in_at', '<', $endExclusive->copy()->addDays(2))
            ->where('clock_out_at', '>=', $start->copy()->subDays(2))
            ->orderBy('clock_in_at')
            ->get();

        $netMinutes = 0;
        $sessionIds = [];

        foreach ($sessions as $session) {
            $minutes = $this->sessionNetMinutesWithin($session, $start, $endExclusive);

            if ($minutes <= 0) {
                continue;
            }

            $netMinutes += $minutes;
            $sessionIds[] = $session->id;
        }

        $leaves = EmployeeLeaveRequest::query()
            ->with('leaveType')
            ->where('employee_profile_id', $employee->id)
            ->where('status', EmployeeLeaveRequest::STATUS_APPROVED)
            ->where('starts_on', '<=', $period->ends_on)
            ->where('ends_on', '>=', $period->starts_on)
            ->orderBy('starts_on')
            ->get();

        $paidLeaveDays = 0.0;
        $unpaidLeaveDays = 0.0;
        $leaveSnapshots = [];

        foreach ($leaves as $leave) {
            $leaveStart = $leave->starts_on->copy()->max($period->starts_on);
            $leaveEnd = $leave->ends_on->copy()->min($period->ends_on);
            $days = $leaveStart->diffInDays($leaveEnd) + 1;

            if ($leave->leaveType?->is_paid) {
                $paidLeaveDays += $days;
            } else {
                $unpaidLeaveDays += $days;
            }

            $leaveSnapshots[] = [
                'id' => $leave->id,
                'type_code' => $leave->leaveType?->code,
                'paid' => (bool) $leave->leaveType?->is_paid,
                'days_in_period' => $days,
            ];
        }

        $baseRate = (float) $compensation->base_rate;
        $basePay = $compensation->pay_basis === EmployeeCompensation::BASIS_HOURLY
            ? round(($netMinutes / 60) * $baseRate, 2)
            : round($baseRate, 2);

        $entry = PayrollEntry::query()->create([
            'payroll_run_id' => $run->id,
            'employee_profile_id' => $employee->id,
            'employee_code_snapshot' => $employee->employee_code,
            'employee_name_snapshot' => $employee->user?->name ?: $employee->employee_code,
            'pay_basis_snapshot' => $compensation->pay_basis,
            'base_rate_snapshot' => $baseRate,
            'currency_snapshot' => $compensation->currency,
            'attendance_session_count' => count($sessionIds),
            'net_work_minutes' => $netMinutes,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'base_pay' => $basePay,
            'gross_pay' => $basePay,
            'net_pay' => $basePay,
            'calculation_snapshot' => [
                'version' => 1,
                'period' => [
                    'starts_on' => $period->starts_on->toDateString(),
                    'ends_on' => $period->ends_on->toDateString(),
                    'pay_date' => $period->pay_date?->toDateString(),
                ],
                'compensation' => [
                    'id' => $compensation->id,
                    'pay_basis' => $compensation->pay_basis,
                    'base_rate' => $baseRate,
                    'currency' => $compensation->currency,
                    'overtime_eligible' => (bool) $compensation->overtime_eligible,
                    'overtime_rate_multiplier' => $compensation->overtime_rate_multiplier !== null
                        ? (float) $compensation->overtime_rate_multiplier
                        : null,
                ],
                'attendance_session_ids' => $sessionIds,
                'leave_requests' => $leaveSnapshots,
                'policy' => [
                    'salary_basis' => 'fixed_amount_per_payroll_period',
                    'hourly_basis' => 'effective_net_attendance_minutes',
                    'paid_leave_auto_pay' => false,
                    'unpaid_leave_auto_deduction' => false,
                    'overtime_auto_calculation' => false,
                    'statutory_tax_auto_calculation' => false,
                ],
            ],
        ]);

        return $entry;
    }

    private function sessionNetMinutesWithin(
        EmployeeAttendanceSession $session,
        CarbonInterface $start,
        CarbonInterface $endExclusive,
    ): int {
        $sessionStart = $session->effectiveClockInAt();
        $sessionEnd = $session->effectiveClockOutAt();

        if (! $sessionStart || ! $sessionEnd) {
            return 0;
        }

        $clippedStart = $sessionStart->greaterThan($start) ? $sessionStart->copy() : $start->copy();
        $clippedEnd = $sessionEnd->lessThan($endExclusive) ? $sessionEnd->copy() : $endExclusive->copy();

        if ($clippedEnd->lte($clippedStart)) {
            return 0;
        }

        $gross = $clippedStart->diffInMinutes($clippedEnd);
        $breakMinutes = 0;

        foreach ($session->breaks as $break) {
            if (! $break->starts_at || ! $break->ends_at) {
                continue;
            }

            $breakStart = $break->starts_at->greaterThan($clippedStart)
                ? $break->starts_at->copy()
                : $clippedStart->copy();
            $breakEnd = $break->ends_at->lessThan($clippedEnd)
                ? $break->ends_at->copy()
                : $clippedEnd->copy();

            if ($breakEnd->gt($breakStart)) {
                $breakMinutes += $breakStart->diffInMinutes($breakEnd);
            }
        }

        return max(0, $gross - $breakMinutes);
    }

    private function recalculateEntry(PayrollEntry $entry): PayrollEntry
    {
        $adjustments = PayrollAdjustment::query()
            ->where('payroll_entry_id', $entry->id)
            ->get();

        $overtime = (float) $adjustments->where('type', PayrollAdjustment::TYPE_OVERTIME)->sum('amount');
        $allowances = (float) $adjustments->where('type', PayrollAdjustment::TYPE_ALLOWANCE)->sum('amount');
        $bonuses = (float) $adjustments->where('type', PayrollAdjustment::TYPE_BONUS)->sum('amount');
        $deductions = (float) $adjustments->where('type', PayrollAdjustment::TYPE_DEDUCTION)->sum('amount');

        $gross = round((float) $entry->base_pay + $overtime + $allowances + $bonuses, 2);
        $net = round($gross - $deductions, 2);

        if ($net < 0) {
            throw ValidationException::withMessages([
                'amount' => __('Payroll deductions cannot make net pay negative.'),
            ]);
        }

        $entry->update([
            'overtime_pay' => round($overtime, 2),
            'allowances_total' => round($allowances, 2),
            'bonuses_total' => round($bonuses, 2),
            'deductions_total' => round($deductions, 2),
            'gross_pay' => $gross,
            'net_pay' => $net,
        ]);

        return $entry->fresh();
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
