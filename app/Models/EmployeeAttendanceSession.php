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

    public function isOpen(): bool
    {
        return $this->clock_out_at === null;
    }

    public function durationMinutes(): int
    {
        $end = $this->clock_out_at ?? now();

        return max(0, (int) $this->clock_in_at?->diffInMinutes($end));
    }
}
