<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Coupon;
use App\Models\GrowthAutomationRule;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GrowthAutomationService
{
    public function buildSnapshot(Carbon $from, Carbon $to): array
    {
        return [
            'summary' => $this->buildSummary($from, $to),
            'campaigns' => $this->buildCampaignBlueprints($from, $to),
            'product_opportunities' => $this->buildProductOpportunities($from, $to),
            'coupon_candidates' => $this->buildCouponCandidates(),
            'active_rules' => $this->buildActiveRules(),
            'active_promotions' => Schema::hasTable('promotion_rules') ? PromotionRule::query()->active()->orderByDesc('priority')->limit(6)->get() : collect(),
            'generated_at' => now(),
        ];
    }

    protected function buildSummary(Carbon $from, Carbon $to): array
    {
        $eventScope = AnalyticsEvent::query()->whereBetween('occurred_at', [$from, $to]);

        $sessionStats = (clone $eventScope)
            ->whereNotNull('session_id')
            ->select(
                'session_id',
                DB::raw("SUM(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as add_count"),
                DB::raw("SUM(CASE WHEN event_type = 'checkout_start' THEN 1 ELSE 0 END) as checkout_count"),
                DB::raw("SUM(CASE WHEN event_type = 'purchase_success' THEN 1 ELSE 0 END) as purchase_count")
            )
            ->groupBy('session_id')
            ->get();

        $warmCartSessions = $sessionStats->filter(fn ($row) => (int) $row->add_count > 0 && (int) $row->purchase_count === 0)->count();
        $checkoutDropSessions = $sessionStats->filter(fn ($row) => (int) $row->checkout_count > 0 && (int) $row->purchase_count === 0)->count();

        $repeatCustomers = Order::query()
            ->whereBetween(DB::raw('DATE(COALESCE(placed_at, created_at))'), [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('user_id')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $discountedOrders = Order::query()
            ->whereBetween(DB::raw('DATE(COALESCE(placed_at, created_at))'), [$from->toDateString(), $to->toDateString()])
            ->where('discount_total', '>', 0)
            ->count();

        return [
            'warm_cart_sessions' => (int) $warmCartSessions,
            'checkout_drop_sessions' => (int) $checkoutDropSessions,
            'repeat_customer_candidates' => (int) $repeatCustomers,
            'discounted_orders' => (int) $discountedOrders,
        ];
    }

    protected function buildCampaignBlueprints(Carbon $from, Carbon $to): Collection
    {
        $summary = $this->buildSummary($from, $to);
        $bestCoupon = Coupon::query()
            ->where('is_active', true)
            ->orderByDesc('used_count')
            ->orderByDesc('value')
            ->first();

        return collect([
            [
                'key' => 'cart_recovery',
                'title' => __('Cart recovery burst'),
                'channel' => __('On-site / Email'),
                'audience_size' => $summary['warm_cart_sessions'],
                'objective' => __('Recover shoppers who added to cart but did not purchase.'),
                'message' => __('Bring back warm intent with a lightweight reminder and a trust-focused checkout CTA.'),
                'coupon_code' => $bestCoupon?->code,
                'priority' => 1,
            ],
            [
                'key' => 'checkout_rescue',
                'title' => __('Checkout rescue'),
                'channel' => __('On-site / WhatsApp-ready copy'),
                'audience_size' => $summary['checkout_drop_sessions'],
                'objective' => __('Rescue users who reached checkout and dropped before payment.'),
                'message' => __('Use reassurance around shipping, payment safety, and delivery speed instead of heavy discounts.'),
                'coupon_code' => null,
                'priority' => 2,
            ],
            [
                'key' => 'repeat_customer_push',
                'title' => __('Repeat buyer push'),
                'channel' => __('Email / CRM'),
                'audience_size' => $summary['repeat_customer_candidates'],
                'objective' => __('Lift repeat purchase frequency from your best existing customers.'),
                'message' => __('Bundle replenishment, VIP drops, or category-specific reminders based on recent order behavior.'),
                'coupon_code' => $bestCoupon?->code,
                'priority' => 3,
            ],
        ])->sortBy('priority')->values();
    }

    protected function buildProductOpportunities(Carbon $from, Carbon $to): Collection
    {
        $rows = DB::table('analytics_product_daily_stats as stats')
            ->join('products', 'products.id', '=', 'stats.product_id')
            ->whereBetween('stats.stat_date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'stats.product_id',
                'products.slug',
                DB::raw('MAX(stats.product_name) as product_name'),
                DB::raw('SUM(stats.views) as views'),
                DB::raw('SUM(stats.add_to_cart_count) as add_to_cart_count'),
                DB::raw('SUM(stats.purchases) as purchases'),
                DB::raw('SUM(stats.revenue_gross) as revenue_gross')
            )
            ->groupBy('stats.product_id', 'products.slug')
            ->havingRaw('SUM(stats.views) >= 10')
            ->orderByDesc(DB::raw('SUM(stats.views)'))
            ->limit(8)
            ->get();

        return collect($rows)->map(function ($row) {
            $views = (int) $row->views;
            $adds = (int) $row->add_to_cart_count;
            $purchases = (int) $row->purchases;
            $viewToCart = $views > 0 ? $adds / $views : 0;
            $viewToPurchase = $views > 0 ? $purchases / $views : 0;

            $opportunity = __('Healthy');
            if ($views >= 20 && $purchases === 0) {
                $opportunity = __('Attention but no purchases');
            } elseif ($adds >= 5 && $purchases === 0) {
                $opportunity = __('Strong cart intent, weak checkout finish');
            } elseif ($viewToCart < 0.05) {
                $opportunity = __('Weak add-to-cart rate');
            }

            return (object) [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'slug' => $row->slug,
                'views' => $views,
                'add_to_cart_count' => $adds,
                'purchases' => $purchases,
                'revenue_gross' => (float) $row->revenue_gross,
                'view_to_cart_rate' => $viewToCart,
                'view_to_purchase_rate' => $viewToPurchase,
                'opportunity' => $opportunity,
            ];
        });
    }

    protected function buildCouponCandidates(): Collection
    {
        if (! Schema::hasTable('coupons')) {
            return collect();
        }

        $coupons = Coupon::query()
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN usage_limit IS NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('used_count')
            ->limit(6)
            ->get();

        return $coupons->map(function (Coupon $coupon) {
            return (object) [
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type_label' => $coupon->type_label,
                'value' => (float) $coupon->value,
                'used_count' => (int) $coupon->used_count,
                'usage_limit' => $coupon->usage_limit,
                'remaining_usage' => $coupon->usage_limit !== null ? max(0, (int) $coupon->usage_limit - (int) $coupon->used_count) : null,
            ];
        });
    }

    protected function buildActiveRules(): Collection
    {
        if (! Schema::hasTable('growth_automation_rules')) {
            return collect();
        }

        return GrowthAutomationRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();
    }
}
