<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_attendance_session_id',
        'starts_at',
        'ends_at',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function attendanceSession()
    {
        return $this->belongsTo(EmployeeAttendanceSession::class, 'employee_attendance_session_id');
    }

    public function isOpen(): bool
    {
        return $this->ends_at === null;
    }

    public function durationMinutes(): int
    {
        $end = $this->ends_at ?? now();

        return max(0, (int) $this->starts_at?->diffInMinutes($end));
    }
}
