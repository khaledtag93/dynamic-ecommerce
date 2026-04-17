<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthCustomerScore;
use App\Models\GrowthDelivery;
use App\Models\GrowthExperiment;
use App\Models\GrowthOfferLearningSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GrowthAdaptiveLearningService
{
    public function refreshSnapshots(int $days = 120): void
    {
        if (! Schema::hasTable('growth_offer_learning_snapshots') || ! Schema::hasTable('growth_deliveries')) {
            return;
        }

        $cutoff = now()->subDays(max(30, $days));

        $deliveries = GrowthDelivery::query()
            ->with(['campaign', 'experiment'])
            ->whereIn('status', ['sent', 'delivered', 'simulated'])
            ->where('created_at', '>=', $cutoff)
            ->get();

        $groups = [];

        foreach ($deliveries as $delivery) {
            $payload = $delivery->payload ?? [];
            $score = $delivery->user_id ? GrowthCustomerScore::query()->where('user_id', $delivery->user_id)->first() : null;
            $campaignKey = $delivery->campaign?->campaign_key ?: Arr::get($payload, 'campaign_key');
            $retentionStage = $score?->retention_stage ?: 'unknown';
            $offerBias = $score?->adaptive_offer_preference ?: $score?->offer_bias ?: 'light_nudge';
            $variantKey = $delivery->experiment_variant ?: 'default';
            $offerKey = Arr::get($payload, 'offer_key') ?: Arr::get($payload, 'decision.offer_key') ?: $variantKey;
            $groupKey = implode('|', [$campaignKey, $retentionStage, $offerBias, $variantKey]);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'campaign_id' => $delivery->campaign_id,
                    'experiment_id' => $delivery->experiment_id,
                    'campaign_key' => $campaignKey,
                    'retention_stage' => $retentionStage,
                    'offer_bias' => $offerBias,
                    'offer_key' => $offerKey,
                    'experiment_variant' => $variantKey,
                    'deliveries' => 0,
                    'converted' => 0,
                    'revenue' => 0.0,
                ];
            }

            $groups[$groupKey]['deliveries']++;
            $groups[$groupKey]['converted'] += (int) ($delivery->meta['converted'] ?? 0);
            $groups[$groupKey]['revenue'] += (float) ($delivery->meta['attributed_revenue'] ?? 0);
        }

        GrowthOfferLearningSnapshot::query()->delete();

        $recommended = [];
        foreach ($groups as $row) {
            $deliveriesCount = max(1, (int) $row['deliveries']);
            $conversionRate = ($row['converted'] / $deliveriesCount) * 100;
            $revenuePerDelivery = ((float) $row['revenue']) / $deliveriesCount;
            $learningScore = ($conversionRate * 0.65) + ($revenuePerDelivery * 0.35);
            $recommendationKey = implode('|', [$row['campaign_key'], $row['retention_stage'], $row['offer_bias']]);
            if (! isset($recommended[$recommendationKey]) || $learningScore > $recommended[$recommendationKey]['learning_score']) {
                $recommended[$recommendationKey] = $row + ['learning_score' => $learningScore];
            }
        }

        foreach ($groups as $row) {
            $recommendationKey = implode('|', [$row['campaign_key'], $row['retention_stage'], $row['offer_bias']]);
            $deliveriesCount = max(1, (int) $row['deliveries']);
            $conversionRate = ($row['converted'] / $deliveriesCount) * 100;
            $revenuePerDelivery = ((float) $row['revenue']) / $deliveriesCount;
            $learningScore = ($conversionRate * 0.65) + ($revenuePerDelivery * 0.35);
            GrowthOfferLearningSnapshot::query()->create([
                'campaign_id' => $row['campaign_id'],
                'experiment_id' => $row['experiment_id'],
                'campaign_key' => $row['campaign_key'],
                'retention_stage' => $row['retention_stage'],
                'offer_bias' => $row['offer_bias'],
                'offer_key' => $row['offer_key'],
                'experiment_variant' => $row['experiment_variant'],
                'deliveries' => $row['deliveries'],
                'converted' => $row['converted'],
                'conversion_rate' => round($conversionRate, 2),
                'revenue' => round((float) $row['revenue'], 2),
                'learning_score' => round($learningScore, 2),
                'is_recommended' => ($recommended[$recommendationKey]['experiment_variant'] ?? null) === $row['experiment_variant'],
                'calculated_at' => now(),
                'meta' => ['revenue_per_delivery' => round($revenuePerDelivery, 2)],
            ]);
        }
    }

    public function chooseVariant(GrowthExperiment $experiment, ?GrowthCustomerScore $score = null): ?array
    {
        $variants = collect($experiment->variants ?? [])->filter(fn ($variant) => ! empty($variant['key']))->values();
        if ($variants->isEmpty()) {
            return null;
        }

        $bias = $score?->adaptive_offer_preference ?: $score?->offer_bias;
        $stage = $score?->retention_stage;

        $recommended = Schema::hasTable('growth_offer_learning_snapshots')
            ? GrowthOfferLearningSnapshot::query()
                ->where('campaign_id', $experiment->campaign_id)
                ->when($stage, fn ($query) => $query->where('retention_stage', $stage))
                ->when($bias, fn ($query) => $query->where('offer_bias', $bias))
                ->where('is_recommended', true)
                ->orderByDesc('learning_score')
                ->first()
            : null;

        if ($recommended) {
            $match = $variants->firstWhere('key', $recommended->experiment_variant);
            if ($match) {
                $match['_adaptive_source'] = 'learning';
                $match['_adaptive_score'] = (float) $recommended->learning_score;
                return $match;
            }
        }

        $scored = $variants->map(function ($variant) use ($score) {
            $variantKey = (string) Arr::get($variant, 'key');
            $weight = max(1, (int) Arr::get($variant, 'weight', 1));
            $boost = 0;
            $offerKey = (string) Arr::get($variant, 'offer_key', $variantKey);
            $discountType = (string) Arr::get($variant, 'discount_type', '');

            if ($score) {
                $preferred = (string) ($score->adaptive_offer_preference ?: $score->offer_bias);
                if ($preferred === 'free_shipping' && (str_contains($offerKey, 'shipping') || $discountType === 'shipping')) {
                    $boost += 35;
                }
                if ($preferred === 'discount_percentage' && in_array($discountType, ['percentage', 'percent'], true)) {
                    $boost += 35;
                }
                if ($preferred === 'loyalty_reward' && str_contains($offerKey, 'vip')) {
                    $boost += 30;
                }
                if ($preferred === 'light_nudge' && ((float) $score->churn_risk_score) < 55) {
                    $boost += 20;
                }
            }

            $variant['_adaptive_score'] = $weight + $boost;
            $variant['_adaptive_source'] = 'heuristic';
            return $variant;
        })->sortByDesc('_adaptive_score')->values();

        return $scored->first();
    }

    public function summary(): array
    {
        if (! Schema::hasTable('growth_offer_learning_snapshots')) {
            return ['rows' => 0, 'recommended_rows' => 0, 'top_learning_score' => 0.0, 'top_conversion_rate' => 0.0];
        }

        return [
            'rows' => (int) GrowthOfferLearningSnapshot::query()->count(),
            'recommended_rows' => (int) GrowthOfferLearningSnapshot::query()->where('is_recommended', true)->count(),
            'top_learning_score' => round((float) GrowthOfferLearningSnapshot::query()->max('learning_score'), 2),
            'top_conversion_rate' => round((float) GrowthOfferLearningSnapshot::query()->max('conversion_rate'), 2),
        ];
    }

    public function topRows(int $limit = 8): Collection
    {
        if (! Schema::hasTable('growth_offer_learning_snapshots')) {
            return collect();
        }

        return GrowthOfferLearningSnapshot::query()
            ->with(['campaign', 'experiment'])
            ->orderByDesc('is_recommended')
            ->orderByDesc('learning_score')
            ->limit($limit)
            ->get();
    }
}
