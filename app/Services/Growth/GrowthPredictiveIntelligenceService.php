<?php

namespace App\Services\Growth;

use App\Models\AnalyticsEvent;
use App\Models\GrowthCustomerScore;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GrowthPredictiveIntelligenceService
{
    public function refreshScores(?int $limit = null): void
    {
        if (! Schema::hasTable('growth_customer_scores')) {
            return;
        }

        $users = User::query()
            ->where(function ($query) {
                $query->whereNull('role_as')
                    ->orWhere('role_as', 0);
            })
            ->orderBy('id')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get();

        foreach ($users as $user) {
            $this->refreshUserScore($user);
        }
    }

    public function refreshUserScore(User $user): GrowthCustomerScore
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByRaw('COALESCE(placed_at, created_at) asc')
            ->get();

        $completedOrders = $orders->where('status', Order::STATUS_COMPLETED);
        $latestOrder = $orders->sortByDesc(fn (Order $order) => $order->placed_at ?: $order->created_at)->first();
        $lastOrderAt = $latestOrder?->placed_at ?: $latestOrder?->created_at;
        $daysSinceLastOrder = $lastOrderAt ? now()->diffInDays($lastOrderAt) : 9999;

        $events30 = AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->get();

        $events90 = AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', now()->subDays(90))
            ->get();

        $ordersCount = $orders->count();
        $completedOrdersCount = $completedOrders->count();
        $totalRevenue = (float) $orders->sum('grand_total');
        $averageOrderValue = $ordersCount > 0 ? $totalRevenue / $ordersCount : 0.0;
        $viewCount30d = (int) $events30->where('event_type', AnalyticsEvent::EVENT_VIEW_PRODUCT)->count();
        $cartCount30d = (int) $events30->where('event_type', AnalyticsEvent::EVENT_ADD_TO_CART)->count();
        $checkoutCount30d = (int) $events30->where('event_type', AnalyticsEvent::EVENT_CHECKOUT_START)->count();
        $purchaseCount90d = (int) $events90->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->count();

        $ltvScore = $this->ltvScore($ordersCount, $averageOrderValue, $totalRevenue, $daysSinceLastOrder);
        $engagementScore = $this->engagementScore($viewCount30d, $cartCount30d, $checkoutCount30d, $purchaseCount90d);
        $churnRiskScore = $this->churnRiskScore($ordersCount, $daysSinceLastOrder, $engagementScore, $purchaseCount90d);
        $retentionStage = $this->retentionStage($ordersCount, $ltvScore, $churnRiskScore, $daysSinceLastOrder);
        $nextBestCampaign = $this->nextBestCampaign($retentionStage, $ltvScore, $churnRiskScore, $daysSinceLastOrder);
        $offerBias = $this->offerBias($averageOrderValue, $ordersCount, $churnRiskScore);
        $adaptivePreference = $this->adaptiveOfferPreference($offerBias, $checkoutCount30d, $cartCount30d, $averageOrderValue, $churnRiskScore, $ordersCount);
        $adaptiveConfidence = $this->adaptiveConfidence($engagementScore, $ordersCount, $purchaseCount90d);
        $winbackPriorityScore = $this->winbackPriorityScore($ltvScore, $churnRiskScore, $daysSinceLastOrder, $ordersCount, $engagementScore);
        $winbackPriorityBand = $this->winbackPriorityBand($winbackPriorityScore);
        $recommendedDiscountType = $this->recommendedDiscountType($adaptivePreference, $churnRiskScore, $averageOrderValue);
        $recommendedDiscountValue = $this->recommendedDiscountValue($recommendedDiscountType, $ltvScore, $churnRiskScore);
        $winbackReadyAt = $nextBestCampaign === 'at_risk_winback' || $nextBestCampaign === 'vip_retention' ? now() : null;

        return GrowthCustomerScore::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'orders_count' => $ordersCount,
                'completed_orders_count' => $completedOrdersCount,
                'total_revenue' => round($totalRevenue, 2),
                'average_order_value' => round($averageOrderValue, 2),
                'days_since_last_order' => $daysSinceLastOrder,
                'last_order_at' => $lastOrderAt,
                'view_count_30d' => $viewCount30d,
                'cart_count_30d' => $cartCount30d,
                'checkout_count_30d' => $checkoutCount30d,
                'purchase_count_90d' => $purchaseCount90d,
                'ltv_score' => $ltvScore,
                'churn_risk_score' => $churnRiskScore,
                'engagement_score' => $engagementScore,
                'retention_stage' => $retentionStage,
                'next_best_campaign' => $nextBestCampaign,
                'offer_bias' => $offerBias,
                'adaptive_offer_preference' => $adaptivePreference,
                'adaptive_confidence' => $adaptiveConfidence,
                'winback_priority_score' => $winbackPriorityScore,
                'winback_priority_band' => $winbackPriorityBand,
                'winback_ready_at' => $winbackReadyAt,
                'recommended_discount_type' => $recommendedDiscountType,
                'recommended_discount_value' => $recommendedDiscountValue,
                'meta' => [
                    'email' => $user->email,
                    'name' => $user->name,
                    'has_recent_cart' => $cartCount30d > 0,
                    'has_recent_checkout' => $checkoutCount30d > 0,
                ],
                'calculated_at' => now(),
            ]
        );
    }

    protected function ltvScore(int $ordersCount, float $averageOrderValue, float $totalRevenue, int $daysSinceLastOrder): float
    {
        $ordersComponent = min(35, $ordersCount * 7);
        $aovComponent = min(25, $averageOrderValue / 20);
        $revenueComponent = min(20, $totalRevenue / 100);
        $recencyComponent = max(0, 20 - min(20, $daysSinceLastOrder / 3));

        return round(min(100, $ordersComponent + $aovComponent + $revenueComponent + $recencyComponent), 2);
    }

    protected function engagementScore(int $views, int $carts, int $checkouts, int $purchases90): float
    {
        return round(min(100, ($views * 4) + ($carts * 12) + ($checkouts * 16) + ($purchases90 * 10)), 2);
    }

    protected function churnRiskScore(int $ordersCount, int $daysSinceLastOrder, float $engagementScore, int $purchases90): float
    {
        if ($ordersCount === 0) {
            return round(max(5, 40 - min(35, $engagementScore / 3)), 2);
        }

        $base = min(80, $daysSinceLastOrder * 1.4);
        $loyaltyRelief = min(20, $ordersCount * 3.5);
        $engagementRelief = min(18, $engagementScore / 8);
        $recentPurchaseRelief = min(14, $purchases90 * 4);

        return round(max(0, min(100, $base - $loyaltyRelief - $engagementRelief - $recentPurchaseRelief + 12)), 2);
    }

    protected function retentionStage(int $ordersCount, float $ltvScore, float $churnRiskScore, int $daysSinceLastOrder): string
    {
        if ($ordersCount <= 1 && $daysSinceLastOrder <= 30) {
            return 'new';
        }

        if ($ltvScore >= 75 && $ordersCount >= 4) {
            return $daysSinceLastOrder >= 21 ? 'vip_needs_attention' : 'vip';
        }

        if ($churnRiskScore >= 70 && $ordersCount >= 2) {
            return 'at_risk';
        }

        if ($ordersCount >= 2) {
            return 'loyal';
        }

        return 'active';
    }

    protected function nextBestCampaign(string $retentionStage, float $ltvScore, float $churnRiskScore, int $daysSinceLastOrder): ?string
    {
        return match (true) {
            $retentionStage === 'at_risk' => 'at_risk_winback',
            in_array($retentionStage, ['vip', 'vip_needs_attention'], true) && $daysSinceLastOrder >= 21 => 'vip_retention',
            $retentionStage === 'new' && $daysSinceLastOrder >= 10 => 'repeat_buyer',
            $ltvScore >= 60 && $churnRiskScore >= 55 => 'at_risk_winback',
            default => null,
        };
    }

    protected function offerBias(float $averageOrderValue, int $ordersCount, float $churnRiskScore): string
    {
        return match (true) {
            $ordersCount >= 4 && $averageOrderValue >= 400 => 'loyalty_reward',
            $churnRiskScore >= 70 => 'discount_percentage',
            $averageOrderValue >= 250 => 'free_shipping',
            default => 'light_nudge',
        };
    }


    protected function adaptiveOfferPreference(string $offerBias, int $checkoutCount30d, int $cartCount30d, float $averageOrderValue, float $churnRiskScore, int $ordersCount): string
    {
        return match (true) {
            $checkoutCount30d >= 2 && $churnRiskScore >= 65 => 'discount_percentage',
            $averageOrderValue >= 250 && $cartCount30d >= 1 => 'free_shipping',
            $ordersCount >= 4 => 'loyalty_reward',
            default => $offerBias,
        };
    }

    protected function adaptiveConfidence(float $engagementScore, int $ordersCount, int $purchaseCount90d): float
    {
        return round(min(100, ($engagementScore * 0.45) + ($ordersCount * 6) + ($purchaseCount90d * 8)), 2);
    }

    protected function winbackPriorityScore(float $ltvScore, float $churnRiskScore, int $daysSinceLastOrder, int $ordersCount, float $engagementScore): float
    {
        $score = ($ltvScore * 0.35) + ($churnRiskScore * 0.4) + min(20, $daysSinceLastOrder * 0.35) + min(12, $ordersCount * 2) + min(10, $engagementScore * 0.1);
        return round(min(100, $score), 2);
    }

    protected function winbackPriorityBand(float $priorityScore): string
    {
        return match (true) {
            $priorityScore >= 80 => 'critical',
            $priorityScore >= 60 => 'high',
            $priorityScore >= 40 => 'medium',
            default => 'low',
        };
    }

    protected function recommendedDiscountType(string $adaptivePreference, float $churnRiskScore, float $averageOrderValue): string
    {
        return match (true) {
            $adaptivePreference === 'free_shipping' => 'shipping',
            $adaptivePreference === 'loyalty_reward' => 'fixed',
            $churnRiskScore >= 65 => 'percentage',
            $averageOrderValue < 150 => 'fixed',
            default => 'percentage',
        };
    }

    protected function recommendedDiscountValue(string $discountType, float $ltvScore, float $churnRiskScore): float
    {
        return match ($discountType) {
            'shipping' => 0,
            'fixed' => round(min(120, 15 + ($ltvScore * 0.2) + ($churnRiskScore * 0.1)), 2),
            default => round(min(25, 5 + ($churnRiskScore * 0.12)), 2),
        };
    }

    public function summary(): array
    {
        if (! Schema::hasTable('growth_customer_scores')) {
            return [
                'customers_scored' => 0,
                'avg_ltv_score' => 0.0,
                'avg_churn_risk' => 0.0,
                'vip_customers' => 0,
                'at_risk_customers' => 0,
                'winback_ready' => 0,
            ];
        }

        $query = GrowthCustomerScore::query();

        return [
            'customers_scored' => (int) $query->count(),
            'avg_ltv_score' => round((float) GrowthCustomerScore::query()->avg('ltv_score'), 2),
            'avg_churn_risk' => round((float) GrowthCustomerScore::query()->avg('churn_risk_score'), 2),
            'vip_customers' => (int) GrowthCustomerScore::query()->whereIn('retention_stage', ['vip', 'vip_needs_attention'])->count(),
            'at_risk_customers' => (int) GrowthCustomerScore::query()->where('retention_stage', 'at_risk')->count(),
            'winback_ready' => (int) GrowthCustomerScore::query()->whereNotNull('next_best_campaign')->count(),
            'critical_winback' => (int) GrowthCustomerScore::query()->where('winback_priority_band', 'critical')->count(),
            'high_winback' => (int) GrowthCustomerScore::query()->where('winback_priority_band', 'high')->count(),
            'avg_adaptive_confidence' => round((float) GrowthCustomerScore::query()->avg('adaptive_confidence'), 2),
        ];
    }

    public function topRows(int $limit = 8): Collection
    {
        if (! Schema::hasTable('growth_customer_scores')) {
            return collect();
        }

        return GrowthCustomerScore::query()
            ->with('user')
            ->orderByDesc('winback_priority_score')
            ->orderByDesc('ltv_score')
            ->orderByDesc('churn_risk_score')
            ->limit($limit)
            ->get();
    }
}
