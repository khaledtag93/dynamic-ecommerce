<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'employee_profile_id',
        'employee_code_snapshot',
        'employee_name_snapshot',
        'pay_basis_snapshot',
        'base_rate_snapshot',
        'currency_snapshot',
        'attendance_session_count',
        'net_work_minutes',
        'paid_leave_days',
        'unpaid_leave_days',
        'base_pay',
        'overtime_pay',
        'allowances_total',
        'bonuses_total',
        'deductions_total',
        'gross_pay',
        'net_pay',
        'calculation_snapshot',
    ];

    protected $casts = [
        'base_rate_snapshot' => 'decimal:2',
        'paid_leave_days' => 'decimal:2',
        'unpaid_leave_days' => 'decimal:2',
        'base_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'allowances_total' => 'decimal:2',
        'bonuses_total' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'calculation_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PayrollEntry $entry) {
            $run = PayrollRun::query()->find($entry->payroll_run_id);

            if ($run && ! $run->isDraft()) {
                throw new \LogicException('Finalized payroll runs cannot receive new payroll entries.');
            }
        });

        static::updating(function (PayrollEntry $entry) {
            $run = $entry->payrollRun()->first();

            if ($run && ! $run->isDraft()) {
                throw new \LogicException('Finalized payroll entry history is immutable.');
            }
        });

        static::deleting(function (PayrollEntry $entry) {
            $run = $entry->payrollRun()->first();

            if ($run && ! $run->isDraft()) {
                throw new \LogicException('Finalized payroll entry history cannot be deleted.');
            }
        });
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function adjustments()
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
