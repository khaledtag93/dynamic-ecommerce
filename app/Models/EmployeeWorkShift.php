<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWorkShift extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_profile_id',
        'starts_at',
        'ends_at',
        'status',
        'location',
        'notes',
        'published_at',
        'cancelled_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_PUBLISHED => __('Published'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function durationMinutes(): int
    {
        return max(0, (int) $this->starts_at?->diffInMinutes($this->ends_at));
    }
}
