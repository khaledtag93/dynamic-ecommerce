<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeeProfile extends Model
{
    use HasFactory;

    public const TYPE_FULL_TIME = 'full_time';
    public const TYPE_PART_TIME = 'part_time';
    public const TYPE_CONTRACTOR = 'contractor';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'user_id',
        'employee_code',
        'job_title',
        'department',
        'employment_type',
        'status',
        'hire_date',
        'termination_date',
        'phone',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeProfile $employee) {
            $employee->employee_code = Str::upper(trim((string) $employee->employee_code));
            $employee->job_title = self::nullableTrim($employee->job_title);
            $employee->department = self::nullableTrim($employee->department);
            $employee->phone = self::nullableTrim($employee->phone);
        });
    }

    public static function employmentTypeOptions(): array
    {
        return [
            self::TYPE_FULL_TIME => __('Full time'),
            self::TYPE_PART_TIME => __('Part time'),
            self::TYPE_CONTRACTOR => __('Contractor'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => __('Active'),
            self::STATUS_ON_LEAVE => __('On leave'),
            self::STATUS_INACTIVE => __('Inactive'),
            self::STATUS_TERMINATED => __('Terminated'),
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(EmployeeAttendanceSession::class);
    }

    public function openAttendanceSession()
    {
        return $this->hasOne(EmployeeAttendanceSession::class)
            ->whereNull('clock_out_at')
            ->latestOfMany();
    }

    public function workShifts()
    {
        return $this->hasMany(EmployeeWorkShift::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function leaveAdjustments()
    {
        return $this->hasMany(EmployeeLeaveAdjustment::class);
    }

    public function compensation()
    {
        return $this->hasOne(EmployeeCompensation::class);
    }

    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function posCashShifts()
    {
        return $this->hasMany(PosCashShift::class, 'cashier_user_id', 'user_id');
    }

    public function canClockTime(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
