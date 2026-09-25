<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeeCompensation extends Model
{
    use HasFactory;

    public const BASIS_SALARY = 'salary';
    public const BASIS_HOURLY = 'hourly';

    protected $table = 'employee_compensations';

    protected $fillable = [
        'employee_profile_id',
        'pay_basis',
        'base_rate',
        'currency',
        'effective_from',
        'effective_to',
        'overtime_eligible',
        'overtime_rate_multiplier',
        'notes',
    ];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'overtime_eligible' => 'boolean',
        'overtime_rate_multiplier' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeCompensation $compensation) {
            $compensation->currency = Str::upper(trim((string) $compensation->currency));
            $compensation->notes = self::nullableTrim($compensation->notes);

            if (! $compensation->overtime_eligible) {
                $compensation->overtime_rate_multiplier = null;
            }
        });
    }

    public static function payBasisOptions(): array
    {
        return [
            self::BASIS_SALARY => __('Salary per payroll period'),
            self::BASIS_HOURLY => __('Hourly'),
        ];
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function isEffectiveFor(PayrollPeriod $period): bool
    {
        return $this->effective_from->lte($period->ends_on)
            && (! $this->effective_to || $this->effective_to->gte($period->starts_on));
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
