<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveAdjustment extends Model
{
    use HasFactory;

    public const TYPE_OPENING = 'opening_balance';
    public const TYPE_CARRYOVER = 'carryover';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'employee_profile_id',
        'employee_leave_type_id',
        'year',
        'type',
        'days',
        'reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'days' => 'decimal:2',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_OPENING => __('Opening balance'),
            self::TYPE_CARRYOVER => __('Carryover'),
            self::TYPE_ADJUSTMENT => __('Adjustment'),
        ];
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(EmployeeLeaveType::class, 'employee_leave_type_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
