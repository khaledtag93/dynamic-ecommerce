<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendanceBreak;
use App\Models\EmployeeAttendanceCorrection;
use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\PayrollAdjustment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforcePayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_default_roles_keep_payroll_management_scoped_to_finance_and_super_admin(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $operations = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $finance = $this->staffWithRole('finance_manager');

        $this->assertFalse($operations->hasPermission('workforce.payroll.view'));
        $this->assertFalse($operations->hasPermission('workforce.payroll.manage'));

        $this->assertTrue($cashier->hasPermission('workforce.payroll.self'));
        $this->assertFalse($cashier->hasPermission('workforce.payroll.view'));

        $this->assertTrue($finance->hasPermission('workforce.payroll.self'));
        $this->assertTrue($finance->hasPermission('workforce.payroll.view'));
        $this->assertTrue($finance->hasPermission('workforce.payroll.manage'));
    }

    public function test_finance_manager_can_save_compensation_with_currency_normalization_and_audit(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-PAY-1');

        $this->actingAs($finance)
            ->put(route('admin.workforce.payroll.compensation.update', $employee), [
                'pay_basis' => EmployeeCompensation::BASIS_HOURLY,
                'base_rate' => 125.50,
                'currency' => 'egp',
                'effective_from' => '2026-09-01',
                'overtime_eligible' => 1,
                'overtime_rate_multiplier' => 1.5,
                'notes' => 'Hourly cashier compensation',
            ])
            ->assertRedirect(route('admin.workforce.payroll.compensation.index'))
            ->assertSessionHas('success');

        $compensation = EmployeeCompensation::query()->firstOrFail();

        $this->assertSame('EGP', $compensation->currency);
        $this->assertSame(EmployeeCompensation::BASIS_HOURLY, $compensation->pay_basis);
        $this->assertSame('125.50', $compensation->base_rate);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $finance->id,
            'action' => 'compensation_created',
            'subject_id' => $compensation->id,
        ]);
    }

    public function test_payroll_periods_cannot_overlap(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');

        $this->actingAs($finance)
            ->post(route('admin.workforce.payroll.periods.store'), [
                'name' => 'First half',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-09-15',
                'pay_date' => '2026-09-16',
            ])
            ->assertRedirect(route('admin.workforce.payroll.index'));

        $this->post(route('admin.workforce.payroll.periods.store'), [
            'name' => 'Overlap',
            'starts_on' => '2026-09-15',
            'ends_on' => '2026-09-30',
            'pay_date' => '2026-10-01',
        ])->assertSessionHasErrors('starts_on');

        $this->assertDatabaseCount('payroll_periods', 1);
    }

    public function test_hourly_payroll_uses_effective_attendance_breaks_and_leave_snapshot_without_auto_monetizing_leave(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-HOURLY-1');

        $this->compensation($employee, EmployeeCompensation::BASIS_HOURLY, 100, 'EGP', true);

        $session = EmployeeAttendanceSession::query()->create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => '2026-09-22 09:15:00',
            'clock_out_at' => '2026-09-22 17:00:00',
            'source' => 'admin',
        ]);

        EmployeeAttendanceBreak::query()->create([
            'employee_attendance_session_id' => $session->id,
            'starts_at' => '2026-09-22 12:00:00',
            'ends_at' => '2026-09-22 12:30:00',
        ]);

        EmployeeAttendanceCorrection::query()->create([
            'employee_attendance_session_id' => $session->id,
            'employee_profile_id' => $employee->id,
            'previous_clock_in_at' => '2026-09-22 09:15:00',
            'previous_clock_out_at' => '2026-09-22 17:00:00',
            'requested_clock_in_at' => '2026-09-22 09:00:00',
            'requested_clock_out_at' => '2026-09-22 17:00:00',
            'reason' => 'Approved terminal correction',
            'status' => EmployeeAttendanceCorrection::STATUS_APPROVED,
            'reviewed_by_user_id' => $finance->id,
            'reviewed_at' => now(),
        ]);

        $paidType = EmployeeLeaveType::query()->create([
            'code' => 'PAID',
            'name' => 'Paid Leave',
            'is_paid' => true,
            'default_annual_entitlement_days' => 20,
            'is_active' => true,
        ]);

        EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $paidType->id,
            'starts_on' => '2026-09-23',
            'ends_on' => '2026-09-23',
            'requested_days' => 1,
            'status' => EmployeeLeaveRequest::STATUS_APPROVED,
            'reviewed_by_user_id' => $finance->id,
            'reviewed_at' => now(),
        ]);

        $period = PayrollPeriod::query()->create([
            'name' => 'Sep payroll',
            'starts_on' => '2026-09-20',
            'ends_on' => '2026-09-24',
            'pay_date' => '2026-09-25',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($finance)
            ->post(route('admin.workforce.payroll.periods.generate', $period))
            ->assertRedirect();

        $entry = PayrollEntry::query()->firstOrFail();

        $this->assertSame(450, $entry->net_work_minutes);
        $this->assertSame('1.00', $entry->paid_leave_days);
        $this->assertSame('0.00', $entry->unpaid_leave_days);
        $this->assertSame('750.00', $entry->base_pay);
        $this->assertSame('750.00', $entry->gross_pay);
        $this->assertSame('750.00', $entry->net_pay);

        $snapshot = $entry->calculation_snapshot;
        $this->assertSame([$session->id], $snapshot['attendance_session_ids']);
        $this->assertFalse($snapshot['policy']['paid_leave_auto_pay']);
        $this->assertFalse($snapshot['policy']['statutory_tax_auto_calculation']);
    }

    public function test_salary_payroll_uses_fixed_period_amount_and_keeps_snapshot_after_compensation_changes(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-SALARY-1');
        $compensation = $this->compensation($employee, EmployeeCompensation::BASIS_SALARY, 5000, 'USD');

        $period = PayrollPeriod::query()->create([
            'name' => 'Salary period',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-24',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($finance)
            ->post(route('admin.workforce.payroll.periods.generate', $period))
            ->assertRedirect();

        $entry = PayrollEntry::query()->firstOrFail();

        $this->assertSame('5000.00', $entry->base_pay);
        $this->assertSame('USD', $entry->currency_snapshot);

        $compensation->update([
            'base_rate' => 6000,
            'currency' => 'EUR',
        ]);

        $entry->refresh();

        $this->assertSame('5000.00', $entry->base_rate_snapshot);
        $this->assertSame('5000.00', $entry->base_pay);
        $this->assertSame('USD', $entry->currency_snapshot);
    }

    public function test_generation_blocks_unstable_attendance_corrections_and_leave_inputs(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-BLOCK-1');
        $this->compensation($employee, EmployeeCompensation::BASIS_SALARY, 3000, 'EGP');

        $session = EmployeeAttendanceSession::query()->create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => '2026-09-22 09:00:00',
            'clock_out_at' => '2026-09-22 17:00:00',
            'source' => 'admin',
        ]);

        EmployeeAttendanceCorrection::query()->create([
            'employee_attendance_session_id' => $session->id,
            'employee_profile_id' => $employee->id,
            'previous_clock_in_at' => '2026-09-22 09:00:00',
            'previous_clock_out_at' => '2026-09-22 17:00:00',
            'requested_clock_in_at' => '2026-09-22 08:50:00',
            'requested_clock_out_at' => '2026-09-22 17:00:00',
            'reason' => 'Pending',
            'status' => EmployeeAttendanceCorrection::STATUS_PENDING,
        ]);

        $period = PayrollPeriod::query()->create([
            'name' => 'Blocked correction',
            'starts_on' => '2026-09-20',
            'ends_on' => '2026-09-24',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($finance)
            ->post(route('admin.workforce.payroll.periods.generate', $period))
            ->assertSessionHasErrors('payroll');

        EmployeeAttendanceCorrection::query()->delete();

        $leaveType = EmployeeLeaveType::query()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual',
            'is_paid' => true,
            'default_annual_entitlement_days' => 20,
            'is_active' => true,
        ]);

        EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $leaveType->id,
            'starts_on' => '2026-09-23',
            'ends_on' => '2026-09-23',
            'requested_days' => 1,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);

        $this->post(route('admin.workforce.payroll.periods.generate', $period))
            ->assertSessionHasErrors('payroll');

        $this->assertDatabaseCount('payroll_runs', 0);
    }

    public function test_future_or_current_unfinished_period_cannot_be_generated(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-FUTURE-1');
        $this->compensation($employee, EmployeeCompensation::BASIS_SALARY, 3000, 'EGP');

        $period = PayrollPeriod::query()->create([
            'name' => 'Current unfinished',
            'starts_on' => '2026-09-25',
            'ends_on' => '2026-09-25',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($finance)
            ->post(route('admin.workforce.payroll.periods.generate', $period))
            ->assertSessionHasErrors('payroll');

        $this->assertDatabaseCount('payroll_runs', 0);
    }

    public function test_adjustments_recalculate_totals_and_approval_makes_run_immutable_then_paid(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-ADJ-1');
        $this->compensation($employee, EmployeeCompensation::BASIS_SALARY, 5000, 'EGP', true);

        $period = PayrollPeriod::query()->create([
            'name' => 'Adjustment period',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-24',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($finance)->post(route('admin.workforce.payroll.periods.generate', $period))->assertRedirect();

        $entry = PayrollEntry::query()->firstOrFail();

        foreach ([
            [PayrollAdjustment::TYPE_OVERTIME, 'Overtime', 300],
            [PayrollAdjustment::TYPE_ALLOWANCE, 'Transport', 100],
            [PayrollAdjustment::TYPE_BONUS, 'Performance', 200],
            [PayrollAdjustment::TYPE_DEDUCTION, 'Documented deduction', 50],
        ] as [$type, $label, $amount]) {
            $this->post(route('admin.workforce.payroll.adjustments.store', $entry), [
                'type' => $type,
                'label' => $label,
                'amount' => $amount,
                'reason' => 'Approved payroll component',
            ])->assertRedirect();
        }

        $entry->refresh();

        $this->assertSame('300.00', $entry->overtime_pay);
        $this->assertSame('100.00', $entry->allowances_total);
        $this->assertSame('200.00', $entry->bonuses_total);
        $this->assertSame('50.00', $entry->deductions_total);
        $this->assertSame('5600.00', $entry->gross_pay);
        $this->assertSame('5550.00', $entry->net_pay);

        $run = $entry->payrollRun()->firstOrFail();

        $this->patch(route('admin.workforce.payroll.runs.approve', $run))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->fresh()->status);
        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $period->fresh()->status);

        $this->post(route('admin.workforce.payroll.adjustments.store', $entry), [
            'type' => PayrollAdjustment::TYPE_BONUS,
            'label' => 'Late bonus',
            'amount' => 10,
            'reason' => 'Should fail',
        ])->assertSessionHasErrors('payroll');

        $this->patch(route('admin.workforce.payroll.runs.paid', $run))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PayrollRun::STATUS_PAID, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->paid_at);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $finance->id,
            'action' => 'payroll_run_approved',
            'subject_id' => $run->id,
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $finance->id,
            'action' => 'payroll_run_paid',
            'subject_id' => $run->id,
        ]);
    }

    public function test_deduction_cannot_make_net_pay_negative(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-NEG-1');
        $this->compensation($employee, EmployeeCompensation::BASIS_SALARY, 100, 'EGP');

        $period = PayrollPeriod::query()->create([
            'name' => 'Negative guard',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-24',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($finance)->post(route('admin.workforce.payroll.periods.generate', $period))->assertRedirect();
        $entry = PayrollEntry::query()->firstOrFail();

        $this->post(route('admin.workforce.payroll.adjustments.store', $entry), [
            'type' => PayrollAdjustment::TYPE_DEDUCTION,
            'label' => 'Too much',
            'amount' => 101,
            'reason' => 'Should rollback',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payroll_adjustments', 0);
        $this->assertSame('100.00', $entry->fresh()->net_pay);
    }

    public function test_employee_sees_only_own_finalized_payslips_and_never_draft_payroll(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $finance = $this->staffWithRole('finance_manager');
        $cashier = $this->staffWithRole('cashier');
        $other = $this->staffWithRole('cashier');

        $employee = $this->employeeFor($cashier, 'EMP-SELF-1');
        $otherEmployee = $this->employeeFor($other, 'EMP-SELF-2');

        $this->compensation($employee, EmployeeCompensation::BASIS_SALARY, 1000, 'EGP');
        $this->compensation($otherEmployee, EmployeeCompensation::BASIS_SALARY, 2000, 'EGP');

        $period = PayrollPeriod::query()->create([
            'name' => 'Self payslip',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-24',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->actingAs($finance)->post(route('admin.workforce.payroll.periods.generate', $period))->assertRedirect();

        $ownEntry = PayrollEntry::where('employee_profile_id', $employee->id)->firstOrFail();
        $otherEntry = PayrollEntry::where('employee_profile_id', $otherEmployee->id)->firstOrFail();

        $this->actingAs($cashier)
            ->get(route('admin.workforce.payroll.my-payslips'))
            ->assertOk()
            ->assertDontSee('Self payslip');

        $this->get(route('admin.workforce.payroll.my-payslip', $ownEntry))->assertNotFound();
        $this->get(route('admin.workforce.payroll.my-payslip', $otherEntry))->assertForbidden();

        $run = $ownEntry->payrollRun()->firstOrFail();

        $this->actingAs($finance)
            ->patch(route('admin.workforce.payroll.runs.approve', $run))
            ->assertRedirect();

        $this->actingAs($cashier)
            ->get(route('admin.workforce.payroll.my-payslips'))
            ->assertOk()
            ->assertSee('Self payslip')
            ->assertSee('1,000.00');

        $this->get(route('admin.workforce.payroll.my-payslip', $ownEntry))
            ->assertOk()
            ->assertSee(__('Payslip'));

        $this->get(route('admin.workforce.payroll.my-payslip', $otherEntry))
            ->assertForbidden();

        $this->get(route('admin.workforce.payroll.index'))
            ->assertForbidden();
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function employeeFor(User $user, string $code): EmployeeProfile
    {
        return EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'job_title' => 'Team Member',
            'department' => 'Retail',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'hire_date' => '2026-01-01',
        ]);
    }

    private function compensation(
        EmployeeProfile $employee,
        string $basis,
        float $rate,
        string $currency,
        bool $overtimeEligible = false,
    ): EmployeeCompensation {
        return EmployeeCompensation::query()->create([
            'employee_profile_id' => $employee->id,
            'pay_basis' => $basis,
            'base_rate' => $rate,
            'currency' => $currency,
            'effective_from' => '2026-01-01',
            'overtime_eligible' => $overtimeEligible,
            'overtime_rate_multiplier' => $overtimeEligible ? 1.5 : null,
        ]);
    }
}
