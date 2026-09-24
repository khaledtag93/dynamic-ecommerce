<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeeLeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'is_paid',
        'default_annual_entitlement_days',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
        'default_annual_entitlement_days' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeLeaveType $type) {
            $type->code = Str::upper(trim((string) $type->code));
            $type->name = trim((string) $type->name);
            $type->name_ar = self::nullableTrim($type->name_ar);
        });
    }

    public function requests()
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function adjustments()
    {
        return $this->hasMany(EmployeeLeaveAdjustment::class);
    }

    public function displayName(): string
    {
        if (app()->getLocale() === 'ar' && $this->name_ar) {
            return $this->name_ar;
        }

        return $this->name;
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
