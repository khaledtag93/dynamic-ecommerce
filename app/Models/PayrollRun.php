<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'payroll_period_id',
        'status',
        'created_by_user_id',
        'approved_by_user_id',
        'paid_by_user_id',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (PayrollRun $run) {
            $originalStatus = (string) $run->getRawOriginal('status');

            if ($originalStatus === self::STATUS_PAID) {
                throw new \LogicException('Paid payroll runs are terminal and immutable.');
            }

            if ($originalStatus === self::STATUS_APPROVED) {
                $dirty = array_keys($run->getDirty());
                $allowed = ['status', 'paid_by_user_id', 'paid_at'];

                if ($run->status !== self::STATUS_PAID || array_diff($dirty, $allowed) !== []) {
                    throw new \LogicException('Approved payroll runs can only transition to Paid.');
                }
            }
        });

        static::deleting(function (PayrollRun $run) {
            if (! $run->isDraft()) {
                throw new \LogicException('Finalized payroll run history cannot be deleted.');
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_PAID => __('Paid'),
        ];
    }

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function entries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
