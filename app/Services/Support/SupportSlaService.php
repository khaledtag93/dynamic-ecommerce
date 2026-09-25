<?php

namespace App\Services\Support;

use App\Models\SupportCase;
use App\Models\WebsiteSetting;
use Carbon\CarbonInterface;

class SupportSlaService
{
    private const FIRST_RESPONSE_DEFAULTS = [
        SupportCase::PRIORITY_LOW => 24,
        SupportCase::PRIORITY_NORMAL => 8,
        SupportCase::PRIORITY_HIGH => 4,
        SupportCase::PRIORITY_URGENT => 1,
    ];

    private const RESOLUTION_DEFAULTS = [
        SupportCase::PRIORITY_LOW => 120,
        SupportCase::PRIORITY_NORMAL => 72,
        SupportCase::PRIORITY_HIGH => 24,
        SupportCase::PRIORITY_URGENT => 8,
    ];

    public function deadlines(string $priority, CarbonInterface $startedAt): array
    {
        return [
            'first_response_due_at' => $startedAt->copy()->addHours($this->firstResponseHours($priority)),
            'resolution_due_at' => $startedAt->copy()->addHours($this->resolutionHours($priority)),
        ];
    }

    public function applyToNewCase(SupportCase $case): void
    {
        $case->forceFill($this->deadlines($case->priority, $case->created_at ?: now()))->save();
    }

    public function recalculateForPriority(SupportCase $case): void
    {
        $deadlines = $this->deadlines($case->priority, $case->created_at ?: now());
        $changes = [];

        if (! $case->first_response_at) {
            $changes['first_response_due_at'] = $deadlines['first_response_due_at'];
        }

        if (! in_array($case->status, [SupportCase::STATUS_RESOLVED, SupportCase::STATUS_CLOSED], true)) {
            $changes['resolution_due_at'] = $deadlines['resolution_due_at'];
        }

        if ($changes !== []) {
            $case->forceFill($changes)->save();
        }
    }

    public function firstResponseHours(string $priority): int
    {
        return $this->hours('first_response', $priority, self::FIRST_RESPONSE_DEFAULTS[$priority] ?? self::FIRST_RESPONSE_DEFAULTS[SupportCase::PRIORITY_NORMAL]);
    }

    public function resolutionHours(string $priority): int
    {
        return $this->hours('resolution', $priority, self::RESOLUTION_DEFAULTS[$priority] ?? self::RESOLUTION_DEFAULTS[SupportCase::PRIORITY_NORMAL]);
    }

    private function hours(string $metric, string $priority, int $default): int
    {
        $value = (int) WebsiteSetting::getValue("support_sla_{$metric}_{$priority}_hours", (string) $default);

        return max(1, min(720, $value));
    }
}
