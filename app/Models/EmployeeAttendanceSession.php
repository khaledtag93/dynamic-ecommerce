<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'clock_in_at',
        'clock_out_at',
        'source',
        'clock_in_notes',
        'clock_out_notes',
    ];

    protected $casts = [
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function breaks()
    {
        return $this->hasMany(EmployeeAttendanceBreak::class, 'employee_attendance_session_id');
    }

    public function openBreak()
    {
        return $this->hasOne(EmployeeAttendanceBreak::class, 'employee_attendance_session_id')
            ->whereNull('ends_at')
            ->latestOfMany();
    }

    public function corrections()
    {
        return $this->hasMany(EmployeeAttendanceCorrection::class, 'employee_attendance_session_id');
    }

    public function approvedCorrection()
    {
        return $this->hasOne(EmployeeAttendanceCorrection::class, 'employee_attendance_session_id')
            ->where('status', EmployeeAttendanceCorrection::STATUS_APPROVED)
            ->latestOfMany();
    }

    public function pendingCorrection()
    {
        return $this->hasOne(EmployeeAttendanceCorrection::class, 'employee_attendance_session_id')
            ->where('status', EmployeeAttendanceCorrection::STATUS_PENDING)
            ->latestOfMany();
    }

    public function isOpen(): bool
    {
        return $this->clock_out_at === null;
    }

    public function effectiveClockInAt()
    {
        $correction = $this->relationLoaded('approvedCorrection')
            ? $this->getRelation('approvedCorrection')
            : $this->approvedCorrection()->first();

        return $correction?->requested_clock_in_at ?? $this->clock_in_at;
    }

    public function effectiveClockOutAt()
    {
        $correction = $this->relationLoaded('approvedCorrection')
            ? $this->getRelation('approvedCorrection')
            : $this->approvedCorrection()->first();

        return $correction?->requested_clock_out_at ?? $this->clock_out_at;
    }

    public function hasApprovedCorrection(): bool
    {
        if ($this->relationLoaded('approvedCorrection')) {
            return $this->getRelation('approvedCorrection') !== null;
        }

        return $this->approvedCorrection()->exists();
    }

    public function durationMinutes(): int
    {
        $end = $this->clock_out_at ?? now();

        return max(0, (int) $this->clock_in_at?->diffInMinutes($end));
    }

    public function effectiveDurationMinutes(): int
    {
        $start = $this->effectiveClockInAt();
        $end = $this->effectiveClockOutAt() ?? now();

        return max(0, (int) $start?->diffInMinutes($end));
    }

    public function breakMinutes(): int
    {
        $breaks = $this->relationLoaded('breaks')
            ? $this->getRelation('breaks')
            : $this->breaks()->get();

        return (int) $breaks->sum(fn (EmployeeAttendanceBreak $break) => $break->durationMinutes());
    }

    public function netWorkedMinutes(): int
    {
        return max(0, $this->effectiveDurationMinutes() - $this->breakMinutes());
    }
}
