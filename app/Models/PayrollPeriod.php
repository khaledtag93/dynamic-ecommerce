<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'pay_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'pay_date' => 'date',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => __('Open'),
            self::STATUS_CLOSED => __('Closed'),
        ];
    }

    public function payrollRun()
    {
        return $this->hasOne(PayrollRun::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
