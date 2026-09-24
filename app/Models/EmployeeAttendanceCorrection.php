<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceCorrection extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_attendance_session_id',
        'employee_profile_id',
        'previous_clock_in_at',
        'previous_clock_out_at',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'reason',
        'status',
        'review_notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'previous_clock_in_at' => 'datetime',
        'previous_clock_out_at' => 'datetime',
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
        ];
    }

    public function attendanceSession()
    {
        return $this->belongsTo(EmployeeAttendanceSession::class, 'employee_attendance_session_id');
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
