<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    use HasFactory;

    public const TYPE_OVERTIME = 'overtime';
    public const TYPE_ALLOWANCE = 'allowance';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_DEDUCTION = 'deduction';

    protected $fillable = [
        'payroll_entry_id',
        'type',
        'label',
        'amount',
        'quantity',
        'rate',
        'reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'decimal:3',
        'rate' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        $guardFinalizedRun = function (PayrollAdjustment $adjustment, string $message): void {
            $entry = PayrollEntry::query()->find($adjustment->payroll_entry_id);
            $run = $entry?->payrollRun()->first();

            if ($run && ! $run->isDraft()) {
                throw new \LogicException($message);
            }
        };

        static::creating(fn (PayrollAdjustment $adjustment) => $guardFinalizedRun(
            $adjustment,
            'Finalized payroll runs cannot receive new adjustments.'
        ));
        static::updating(fn (PayrollAdjustment $adjustment) => $guardFinalizedRun(
            $adjustment,
            'Finalized payroll adjustment history is immutable.'
        ));
        static::deleting(fn (PayrollAdjustment $adjustment) => $guardFinalizedRun(
            $adjustment,
            'Finalized payroll adjustment history cannot be deleted.'
        ));
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_OVERTIME => __('Overtime'),
            self::TYPE_ALLOWANCE => __('Allowance'),
            self::TYPE_BONUS => __('Bonus'),
            self::TYPE_DEDUCTION => __('Deduction'),
        ];
    }

    public function payrollEntry()
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_OVERTIME,
            self::TYPE_ALLOWANCE,
            self::TYPE_BONUS,
        ], true);
    }
}
