<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

final class WorkforceClock
{
    public static function timezone(): string
    {
        $name = (string) config('workforce.timezone', 'Africa/Cairo');

        return in_array($name, DateTimeZone::listIdentifiers(), true) || $name === 'UTC'
            ? $name
            : 'Africa/Cairo';
    }

    public static function local(?DateTimeInterface $utc, string $pattern = 'd/m/Y H:i'): ?string
    {
        return $utc
            ? CarbonImmutable::instance($utc)->setTimezone(self::timezone())->format($pattern)
            : null;
    }

    public static function input(?DateTimeInterface $utc): ?string
    {
        return self::local($utc, 'Y-m-d\TH:i');
    }

    public static function fromLocal(string $value, string $field): string
    {
        $value = trim($value);
        // Browsers send datetime-local (with T); older internal callers also
        // send space-separated seconds. Both are interpreted in work timezone.
        $format = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)
            ? '!Y-m-d\TH:i'
            : '!Y-m-d H:i:s';
        $plainFormat = ltrim($format, '!');
        try {
            $date = CarbonImmutable::createFromFormat($format, $value, self::timezone());
        } catch (\Throwable) {
            $date = null;
        }

        // A skipped DST hour must never silently become a different clock time.
        if (! $date || $date->format($plainFormat) !== $value) {
            throw ValidationException::withMessages([
                $field => __('Enter a valid local date and time for the selected timezone.'),
            ]);
        }

        return $date->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    /** Local calendar day as a half-open UTC window; survives 23/25 hour DST days. */
    public static function localDayUtcBounds(string $date): array
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());
        } catch (\Throwable) {
            $start = null;
        }
        if (! $start || $start->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => __('Invalid date.')]);
        }

        return [
            $start->setTimezone('UTC')->format('Y-m-d H:i:s'),
            $start->addDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ];
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::timezone())->format('Y-m-d');
    }
}
