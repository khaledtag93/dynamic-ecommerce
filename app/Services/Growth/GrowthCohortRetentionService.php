<?php

namespace App\Services\Growth;

use App\Models\GrowthCohortSnapshot;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GrowthCohortRetentionService
{
    public function refreshSnapshots(int $months = 6): void
    {
        if (! Schema::hasTable('growth_cohort_snapshots')) {
            return;
        }

        $months = max(3, $months);
        $orders = Order::query()
            ->whereNotNull('user_id')
            ->orderByRaw('COALESCE(placed_at, created_at) asc')
            ->get()
            ->groupBy('user_id');

        $cohorts = [];

        foreach ($orders as $userOrders) {
            $sorted = $userOrders->sortBy(fn (Order $order) => $order->placed_at ?: $order->created_at)->values();
            $first = $sorted->first();

            if (! $first) {
                continue;
            }

            $cohortStart = ($first->placed_at ?: $first->created_at)->copy()->startOfMonth();
            if ($cohortStart->lt(now()->copy()->subMonths($months - 1)->startOfMonth())) {
                continue;
            }

            $key = $cohortStart->format('Y-m');
            if (! isset($cohorts[$key])) {
                $cohorts[$key] = [
                    'cohort_key' => $key,
                    'cohort_label' => $cohortStart->translatedFormat('M Y'),
                    'cohort_start_date' => $cohortStart->toDateString(),
                    'cohort_end_date' => $cohortStart->copy()->endOfMonth()->toDateString(),
                    'cohort_size' => 0,
                    'retained_30d' => 0,
                    'retained_60d' => 0,
                    'retained_90d' => 0,
                    'revenue_30d' => 0.0,
                    'revenue_60d' => 0.0,
                    'revenue_90d' => 0.0,
                    'meta' => ['user_ids' => []],
                ];
            }

            $cohorts[$key]['cohort_size']++;
            $cohorts[$key]['meta']['user_ids'][] = $first->user_id;

            $firstAt = $first->placed_at ?: $first->created_at;

            $has30 = false;
            $has60 = false;
            $has90 = false;

            foreach ($sorted->skip(1) as $repeatOrder) {
                $repeatAt = $repeatOrder->placed_at ?: $repeatOrder->created_at;
                $days = $firstAt->diffInDays($repeatAt);

                if ($days <= 30 && ! $has30) {
                    $cohorts[$key]['retained_30d']++;
                    $cohorts[$key]['revenue_30d'] += (float) $repeatOrder->grand_total;
                    $has30 = true;
                }

                if ($days <= 60 && ! $has60) {
                    $cohorts[$key]['retained_60d']++;
                    $cohorts[$key]['revenue_60d'] += (float) $repeatOrder->grand_total;
                    $has60 = true;
                }

                if ($days <= 90 && ! $has90) {
                    $cohorts[$key]['retained_90d']++;
                    $cohorts[$key]['revenue_90d'] += (float) $repeatOrder->grand_total;
                    $has90 = true;
                }

                if ($has30 && $has60 && $has90) {
                    break;
                }
            }
        }

        foreach ($cohorts as $snapshot) {
            $size = max(1, (int) $snapshot['cohort_size']);
            $snapshot['retention_rate_30d'] = round(((int) $snapshot['retained_30d'] / $size) * 100, 2);
            $snapshot['retention_rate_60d'] = round(((int) $snapshot['retained_60d'] / $size) * 100, 2);
            $snapshot['retention_rate_90d'] = round(((int) $snapshot['retained_90d'] / $size) * 100, 2);
            $snapshot['revenue_30d'] = round((float) $snapshot['revenue_30d'], 2);
            $snapshot['revenue_60d'] = round((float) $snapshot['revenue_60d'], 2);
            $snapshot['revenue_90d'] = round((float) $snapshot['revenue_90d'], 2);
            $snapshot['calculated_at'] = now();

            GrowthCohortSnapshot::query()->updateOrCreate(
                ['cohort_key' => $snapshot['cohort_key']],
                $snapshot
            );
        }
    }

    public function summary(): array
    {
        if (! Schema::hasTable('growth_cohort_snapshots')) {
            return [
                'average_retention_30d' => 0.0,
                'average_retention_60d' => 0.0,
                'average_retention_90d' => 0.0,
                'cohort_revenue_90d' => 0.0,
            ];
        }

        $rows = GrowthCohortSnapshot::query()->latest('cohort_start_date')->limit(6)->get();

        return [
            'average_retention_30d' => round((float) $rows->avg('retention_rate_30d'), 2),
            'average_retention_60d' => round((float) $rows->avg('retention_rate_60d'), 2),
            'average_retention_90d' => round((float) $rows->avg('retention_rate_90d'), 2),
            'cohort_revenue_90d' => round((float) $rows->sum('revenue_90d'), 2),
        ];
    }

    public function latestRows(int $limit = 6): Collection
    {
        if (! Schema::hasTable('growth_cohort_snapshots')) {
            return collect();
        }

        return GrowthCohortSnapshot::query()
            ->latest('cohort_start_date')
            ->limit($limit)
            ->get();
    }
}
