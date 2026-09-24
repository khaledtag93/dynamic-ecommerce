<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollAdjustment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\Workforce\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payrollService)
    {
    }

    public function index()
    {
        $periods = PayrollPeriod::query()
            ->with(['payrollRun.entries'])
            ->latest('starts_on')
            ->paginate(20);

        $stats = [
            'open_periods' => PayrollPeriod::where('status', PayrollPeriod::STATUS_OPEN)->count(),
            'draft_runs' => PayrollRun::where('status', PayrollRun::STATUS_DRAFT)->count(),
            'approved_runs' => PayrollRun::where('status', PayrollRun::STATUS_APPROVED)->count(),
            'paid_runs' => PayrollRun::where('status', PayrollRun::STATUS_PAID)->count(),
        ];

        return view('admin.workforce.payroll.index', compact('periods', 'stats'));
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'pay_date' => ['nullable', 'date', 'after_or_equal:ends_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->payrollService->createPeriod($data, $request->user());

        return redirect()
            ->route('admin.workforce.payroll.index')
            ->with('success', __('Payroll period created.'));
    }

    public function generate(Request $request, PayrollPeriod $payrollPeriod)
    {
        $run = $this->payrollService->generateRun($payrollPeriod, $request->user());

        return redirect()
            ->route('admin.workforce.payroll.runs.show', $run)
            ->with('success', __('Payroll draft generated.'));
    }

    public function showRun(PayrollRun $payrollRun)
    {
        $payrollRun->load([
            'period',
            'createdBy',
            'approvedBy',
            'paidBy',
            'entries.employee.user',
            'entries.adjustments.createdBy',
        ]);

        $totalsByCurrency = $payrollRun->entries
            ->groupBy('currency_snapshot')
            ->map(function ($entries, $currency) {
                return [
                    'currency' => $currency,
                    'employees' => $entries->count(),
                    'base' => (float) $entries->sum('base_pay'),
                    'overtime' => (float) $entries->sum('overtime_pay'),
                    'allowances' => (float) $entries->sum('allowances_total'),
                    'bonuses' => (float) $entries->sum('bonuses_total'),
                    'deductions' => (float) $entries->sum('deductions_total'),
                    'gross' => (float) $entries->sum('gross_pay'),
                    'net' => (float) $entries->sum('net_pay'),
                ];
            })
            ->values();

        return view('admin.workforce.payroll.run', compact('payrollRun', 'totalsByCurrency'));
    }

    public function addAdjustment(Request $request, PayrollEntry $payrollEntry)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(PayrollAdjustment::typeOptions()))],
            'label' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'rate' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.9999'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->payrollService->addAdjustment($payrollEntry, $data, $request->user());

        return redirect()
            ->route('admin.workforce.payroll.runs.show', $payrollEntry->payroll_run_id)
            ->with('success', __('Payroll adjustment added.'));
    }

    public function removeAdjustment(Request $request, PayrollAdjustment $payrollAdjustment)
    {
        $entry = $payrollAdjustment->payrollEntry()->firstOrFail();

        $this->payrollService->removeAdjustment($payrollAdjustment, $request->user());

        return redirect()
            ->route('admin.workforce.payroll.runs.show', $entry->payroll_run_id)
            ->with('success', __('Payroll adjustment removed.'));
    }

    public function approve(Request $request, PayrollRun $payrollRun)
    {
        $this->payrollService->approveRun($payrollRun, $request->user());

        return redirect()
            ->route('admin.workforce.payroll.runs.show', $payrollRun)
            ->with('success', __('Payroll run approved.'));
    }

    public function markPaid(Request $request, PayrollRun $payrollRun)
    {
        $this->payrollService->markPaid($payrollRun, $request->user());

        return redirect()
            ->route('admin.workforce.payroll.runs.show', $payrollRun)
            ->with('success', __('Payroll run marked Paid.'));
    }

    public function showEntry(PayrollEntry $payrollEntry)
    {
        $payrollEntry->load([
            'payrollRun.period',
            'employee.user',
            'adjustments.createdBy',
        ]);

        return view('admin.workforce.payroll.payslip', [
            'entry' => $payrollEntry,
            'selfView' => false,
        ]);
    }

    public function myPayslips(Request $request)
    {
        $employee = $request->user()->employeeProfile()->first();

        $entries = $employee
            ? $employee->payrollEntries()
                ->whereHas('payrollRun', fn ($query) => $query->whereIn('status', [
                    PayrollRun::STATUS_APPROVED,
                    PayrollRun::STATUS_PAID,
                ]))
                ->with('payrollRun.period')
                ->latest('id')
                ->paginate(20)
            : null;

        return view('admin.workforce.payroll.my-payslips', compact('employee', 'entries'));
    }

    public function myPayslip(Request $request, PayrollEntry $payrollEntry)
    {
        $employee = $request->user()->employeeProfile()->first();

        abort_unless(
            $employee && (int) $payrollEntry->employee_profile_id === (int) $employee->id,
            403
        );

        $payrollEntry->load([
            'payrollRun.period',
            'employee.user',
            'adjustments.createdBy',
        ]);

        abort_unless(
            in_array($payrollEntry->payrollRun->status, [
                PayrollRun::STATUS_APPROVED,
                PayrollRun::STATUS_PAID,
            ], true),
            404
        );

        return view('admin.workforce.payroll.payslip', [
            'entry' => $payrollEntry,
            'selfView' => true,
        ]);
    }
}
