<?php

namespace App\Services\Growth;

use App\Models\AnalyticsEvent;
use App\Models\Coupon;
use App\Models\GrowthAttributionTouch;
use App\Models\GrowthAudienceSegment;
use App\Models\GrowthAutomationRule;
use App\Models\GrowthCampaign;
use App\Models\GrowthCouponIssue;
use App\Models\GrowthDelivery;
use App\Models\GrowthExperiment;
use App\Models\GrowthMessageLog;
use App\Models\GrowthCohortSnapshot;
use App\Models\GrowthCustomerScore;
use App\Models\GrowthMessageTemplate;
use App\Models\GrowthOfferLearningSnapshot;
use App\Models\GrowthTriggerLog;
use App\Models\Order;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GrowthCampaignService
{
    public function ensureDefaults(): void
    {
        if (! Schema::hasTable('growth_campaigns') || ! Schema::hasTable('growth_automation_rules')) {
            return;
        }

        if (Schema::hasTable('growth_audience_segments')) {
            foreach ($this->defaultSegments() as $segment) {
                GrowthAudienceSegment::query()->updateOrCreate(
                    ['segment_key' => $segment['segment_key']],
                    $segment
                );
            }
        }

        if (Schema::hasTable('growth_message_templates')) {
            foreach ($this->defaultTemplates() as $template) {
                $this->upsertTemplateSafely($template);
            }
        }

        foreach ($this->defaultCampaigns() as $campaign) {
            GrowthCampaign::query()->updateOrCreate(
                ['campaign_key' => $campaign['campaign_key']],
                $campaign
            );
        }

        foreach ($this->defaultRules() as $rule) {
            GrowthAutomationRule::query()->updateOrCreate(
                ['rule_key' => $rule['rule_key']],
                $rule
            );
        }

        if (Schema::hasTable('growth_experiments')) {
            foreach ($this->defaultExperiments() as $experiment) {
                GrowthExperiment::query()->updateOrCreate(
                    ['experiment_key' => $experiment['experiment_key']],
                    $experiment
                );
            }
        }

        WebsiteSetting::setValue('growth_engine_enabled', WebsiteSetting::getValue('growth_engine_enabled', '1'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_messaging_enabled', WebsiteSetting::getValue('growth_messaging_enabled', '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_real_email_enabled', WebsiteSetting::getValue('growth_real_email_enabled', '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_experiments_enabled', WebsiteSetting::getValue('growth_experiments_enabled', '1'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_delivery_provider', WebsiteSetting::getValue('growth_delivery_provider', config('growth.email_provider', 'smtp')), 'growth', 'string');
        WebsiteSetting::setValue('growth_ai_selection_enabled', WebsiteSetting::getValue('growth_ai_selection_enabled', config('growth.ai_offer_selection_default', true) ? '1' : '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_smart_timing_enabled', WebsiteSetting::getValue('growth_smart_timing_enabled', config('growth.smart_timing_default', true) ? '1' : '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_dynamic_coupons_enabled', WebsiteSetting::getValue('growth_dynamic_coupons_enabled', config('growth.dynamic_coupons_default', true) ? '1' : '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_predictive_enabled', WebsiteSetting::getValue('growth_predictive_enabled', config('growth.predictive_default', true) ? '1' : '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_winback_enabled', WebsiteSetting::getValue('growth_winback_enabled', config('growth.winback_default', true) ? '1' : '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_adaptive_learning_enabled', WebsiteSetting::getValue('growth_adaptive_learning_enabled', config('growth.adaptive_learning_default', true) ? '1' : '0'), 'growth', 'boolean');
        WebsiteSetting::setValue('growth_smarter_winback_enabled', WebsiteSetting::getValue('growth_smarter_winback_enabled', config('growth.smarter_winback_default', true) ? '1' : '0'), 'growth', 'boolean');
    }

    public function dashboardSnapshot(): array
    {
        $this->ensureDefaults();
        app(GrowthAttributionService::class)->syncRecentAttribution();
        app(GrowthCohortRetentionService::class)->refreshSnapshots((int) config('growth.cohort_months', 6));
        if ($this->predictiveEnabled()) {
            app(GrowthPredictiveIntelligenceService::class)->refreshScores();
        }
        if ($this->adaptiveLearningEnabled()) {
            app(GrowthAdaptiveLearningService::class)->refreshSnapshots();
        }

        $campaigns = Schema::hasTable('growth_campaigns')
            ? GrowthCampaign::query()->with(['segment', 'experiments'])->orderBy('priority')->orderBy('id')->get()
            : collect();

        $rules = Schema::hasTable('growth_automation_rules')
            ? GrowthAutomationRule::query()->with('segment')->orderBy('priority')->orderBy('id')->get()
            : collect();

        $templates = Schema::hasTable('growth_message_templates')
            ? GrowthMessageTemplate::query()->orderBy('template_key')->orderBy('locale')->orderBy('priority')->get()
            : collect();

        $segments = Schema::hasTable('growth_audience_segments')
            ? GrowthAudienceSegment::query()->orderBy('priority')->orderBy('name')->get()
            : collect();

        $triggerLogs = Schema::hasTable('growth_trigger_logs')
            ? GrowthTriggerLog::query()->with(['campaign', 'user'])->latest('triggered_at')->latest('id')->limit(20)->get()
            : collect();

        $messageLogs = Schema::hasTable('growth_message_logs')
            ? GrowthMessageLog::query()->with(['campaign', 'user', 'experiment'])->latest('sent_at')->latest('id')->limit(20)->get()
            : collect();

        $deliveries = Schema::hasTable('growth_deliveries')
            ? GrowthDelivery::query()->with(['campaign', 'user', 'experiment'])->latest('created_at')->latest('id')->limit(30)->get()
            : collect();

        $experiments = Schema::hasTable('growth_experiments')
            ? GrowthExperiment::query()->with('campaign')->orderBy('priority')->orderBy('id')->get()
            : collect();

        $attributionSummary = app(GrowthAttributionService::class)->summary();
        $attributionBreakdown = app(GrowthAttributionService::class)->campaignBreakdown();
        $cohortSummary = app(GrowthCohortRetentionService::class)->summary();
        $cohortRows = app(GrowthCohortRetentionService::class)->latestRows();

        return [
            'settings' => [
                'engine_enabled' => $this->engineEnabled(),
                'messaging_enabled' => $this->messagingEnabled(),
                'real_email_enabled' => $this->realEmailEnabled(),
                'experiments_enabled' => $this->experimentsEnabled(),
                'delivery_provider' => $this->deliveryProvider(),
                'supported_delivery_providers' => config('growth.supported_email_providers', ['smtp']),
                'ai_selection_enabled' => $this->aiSelectionEnabled(),
                'smart_timing_enabled' => $this->smartTimingEnabled(),
                'dynamic_coupons_enabled' => $this->dynamicCouponsEnabled(),
                'predictive_enabled' => $this->predictiveEnabled(),
                'winback_enabled' => $this->winbackEnabled(),
                'adaptive_learning_enabled' => $this->adaptiveLearningEnabled(),
                'smarter_winback_enabled' => $this->smarterWinbackEnabled(),
            ],
            'campaigns' => $campaigns,
            'rules' => $rules,
            'templates' => $templates,
            'segments' => $segments,
            'experiments' => $experiments,
            'deliveries' => $deliveries,
            'performance' => $this->buildPerformance(),
            'trigger_logs' => $triggerLogs,
            'message_logs' => $messageLogs,
            'experiment_performance' => $this->experimentPerformanceSummary($experiments),
            'attribution_summary' => $attributionSummary,
            'attribution_breakdown' => $attributionBreakdown,
            'cohort_summary' => $cohortSummary,
            'cohort_rows' => $cohortRows,
            'predictive_summary' => app(GrowthPredictiveIntelligenceService::class)->summary(),
            'predictive_rows' => app(GrowthPredictiveIntelligenceService::class)->topRows(),
            'adaptive_learning_summary' => app(GrowthAdaptiveLearningService::class)->summary(),
            'adaptive_learning_rows' => app(GrowthAdaptiveLearningService::class)->topRows(),
        ];
    }

    public function engineEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_engine_enabled', '1') === '1';
    }

    public function messagingEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_messaging_enabled', '0') === '1';
    }

    public function realEmailEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_real_email_enabled', '0') === '1';
    }

    public function experimentsEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_experiments_enabled', '1') === '1';
    }

    public function aiSelectionEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_ai_selection_enabled', config('growth.ai_offer_selection_default', true) ? '1' : '0') === '1';
    }

    public function smartTimingEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_smart_timing_enabled', config('growth.smart_timing_default', true) ? '1' : '0') === '1';
    }

    public function dynamicCouponsEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_dynamic_coupons_enabled', config('growth.dynamic_coupons_default', true) ? '1' : '0') === '1';
    }

    public function predictiveEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_predictive_enabled', config('growth.predictive_default', true) ? '1' : '0') === '1';
    }

    public function winbackEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_winback_enabled', config('growth.winback_default', true) ? '1' : '0') === '1';
    }

    public function adaptiveLearningEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_adaptive_learning_enabled', config('growth.adaptive_learning_default', true) ? '1' : '0') === '1';
    }

    public function smarterWinbackEnabled(): bool
    {
        return WebsiteSetting::getValue('growth_smarter_winback_enabled', config('growth.smarter_winback_default', true) ? '1' : '0') === '1';
    }

    public function deliveryProvider(): string
    {
        $provider = (string) WebsiteSetting::getValue('growth_delivery_provider', config('growth.email_provider', 'smtp'));

        return in_array($provider, config('growth.supported_email_providers', ['smtp']), true) ? $provider : 'smtp';
    }

    public function updateSettings(array $payload): void
    {
        WebsiteSetting::setValue('growth_engine_enabled', ! empty($payload['growth_engine_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_messaging_enabled', ! empty($payload['growth_messaging_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_real_email_enabled', ! empty($payload['growth_real_email_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_experiments_enabled', ! empty($payload['growth_experiments_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_delivery_provider', Arr::get($payload, 'growth_delivery_provider', 'smtp'), 'growth', 'string');
        WebsiteSetting::setValue('growth_ai_selection_enabled', ! empty($payload['growth_ai_selection_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_smart_timing_enabled', ! empty($payload['growth_smart_timing_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_dynamic_coupons_enabled', ! empty($payload['growth_dynamic_coupons_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_predictive_enabled', ! empty($payload['growth_predictive_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_winback_enabled', ! empty($payload['growth_winback_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_adaptive_learning_enabled', ! empty($payload['growth_adaptive_learning_enabled']) ? '1' : '0', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_smarter_winback_enabled', ! empty($payload['growth_smarter_winback_enabled']) ? '1' : '0', 'growth', 'boolean');
    }

    public function toggleCampaign(GrowthCampaign $campaign): void
    {
        $campaign->update(['is_active' => ! $campaign->is_active]);
    }

    public function toggleCampaignMessaging(GrowthCampaign $campaign): void
    {
        $campaign->update(['is_messaging_enabled' => ! $campaign->is_messaging_enabled]);
    }

    public function toggleRule(GrowthAutomationRule $rule): void
    {
        $rule->update(['is_active' => ! $rule->is_active]);
    }

    public function toggleExperiment(GrowthExperiment $experiment): void
    {
        $experiment->update(['is_active' => ! $experiment->is_active]);
    }

    public function retryDelivery(GrowthDelivery $delivery): void
    {
        app(GrowthDeliveryService::class)->retry($delivery);
    }

    public function runNow(?GrowthCampaign $onlyCampaign = null, int $limit = 100): array
    {
        $this->ensureDefaults();

        if (! $this->engineEnabled()) {
            return ['processed' => 0, 'triggered' => 0, 'messages' => 0, 'skipped' => 0, 'note' => __('Growth engine is currently disabled.')];
        }

        if ($this->predictiveEnabled()) {
            app(GrowthPredictiveIntelligenceService::class)->refreshScores();
        }
        if ($this->adaptiveLearningEnabled()) {
            app(GrowthAdaptiveLearningService::class)->refreshSnapshots();
        }

        $campaigns = GrowthCampaign::query()
            ->with(['segment', 'experiments'])
            ->when($onlyCampaign, fn (Builder $query) => $query->whereKey($onlyCampaign->id))
            ->where('is_active', true)
            ->orderBy('priority')
            ->limit($onlyCampaign ? 1 : 20)
            ->get();

        $result = ['processed' => 0, 'triggered' => 0, 'messages' => 0, 'scheduled' => 0, 'due' => 0, 'skipped' => 0, 'note' => null];

        foreach ($campaigns as $campaign) {
            $candidates = $this->resolveCandidates($campaign, $limit);
            $result['processed'] += $candidates->count();

            foreach ($candidates as $candidate) {
                $decision = $this->processCandidate($campaign, $candidate);
                $result[$decision] = ($result[$decision] ?? 0) + 1;
            }

            $campaign->forceFill(['last_run_at' => now()])->save();
        }

        $result['due'] = app(GrowthDeliveryService::class)->processDuePending();

        return $result;
    }

    protected function processCandidate(GrowthCampaign $campaign, array $candidate): string
    {
        $rule = GrowthAutomationRule::query()
            ->where('rule_key', $campaign->campaign_key)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            $this->logTrigger($campaign, null, $candidate, 'skipped_rule_inactive');

            return 'skipped';
        }

        if ($campaign->segment && ! $campaign->segment->is_active) {
            $this->logTrigger($campaign, $rule, $candidate, 'skipped_segment_inactive');

            return 'skipped';
        }

        if (! $this->segmentMatches($campaign->segment, $candidate)) {
            $this->logTrigger($campaign, $rule, $candidate, 'skipped_segment_filter');

            return 'skipped';
        }

        if ($this->recentlyTriggered($campaign, $candidate)) {
            $this->logTrigger($campaign, $rule, $candidate, 'skipped_cooldown');

            return 'skipped';
        }

        $triggerLog = $this->logTrigger($campaign, $rule, $candidate, 'triggered');

        $campaign->update([
            'stats' => array_merge($campaign->stats ?? [], [
                'last_triggered_at' => now()->toDateTimeString(),
                'trigger_count' => (int) (($campaign->stats['trigger_count'] ?? 0)) + 1,
            ]),
        ]);

        if (! $this->messagingEnabled() || ! $campaign->is_messaging_enabled) {
            return 'triggered';
        }

        $delivery = $this->dispatchMessage($campaign, $triggerLog);

        return $delivery && $delivery->status === 'scheduled' ? 'scheduled' : 'messages';
    }

    protected function logTrigger(GrowthCampaign $campaign, ?GrowthAutomationRule $rule, array $candidate, string $status): GrowthTriggerLog
    {
        return GrowthTriggerLog::query()->create([
            'campaign_id' => $campaign->id,
            'rule_id' => $rule?->id,
            'user_id' => $candidate['user_id'] ?? null,
            'session_id' => $candidate['session_id'] ?? null,
            'trigger_event' => $campaign->trigger_event,
            'status' => $status,
            'channel' => $campaign->channel,
            'audience_snapshot' => $candidate,
            'payload' => [
                'campaign_key' => $campaign->campaign_key,
                'channel' => $campaign->channel,
                'messaging_enabled' => $campaign->is_messaging_enabled,
                'segment_key' => $campaign->segment?->segment_key,
                'template_key' => $campaign->default_template_key,
            ],
            'triggered_at' => now(),
            'processed_at' => now(),
        ]);
    }

    protected function recentlyTriggered(GrowthCampaign $campaign, array $candidate): bool
    {
        $cooldownHours = (int) ($campaign->config['cooldown_hours'] ?? 24);
        $cutoff = now()->subHours(max(1, $cooldownHours));

        return GrowthTriggerLog::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', ['triggered', 'sent', 'delivered'])
            ->where('triggered_at', '>=', $cutoff)
            ->where(function (Builder $query) use ($candidate) {
                if (! empty($candidate['user_id'])) {
                    $query->where('user_id', $candidate['user_id']);
                } elseif (! empty($candidate['session_id'])) {
                    $query->where('session_id', $candidate['session_id']);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->exists();
    }

    protected function resolveCandidates(GrowthCampaign $campaign, int $limit = 100): Collection
    {
        return match ($campaign->campaign_key) {
            'cart_recovery' => $this->cartRecoveryCandidates($campaign, $limit),
            'abandoned_checkout' => $this->abandonedCheckoutCandidates($campaign, $limit),
            'repeat_buyer' => $this->repeatBuyerCandidates($campaign, $limit),
            'high_intent_users' => $this->highIntentCandidates($campaign, $limit),
            'at_risk_winback' => $this->atRiskWinbackCandidates($campaign, $limit),
            'vip_retention' => $this->vipRetentionCandidates($campaign, $limit),
            default => collect(),
        };
    }

    protected function cartRecoveryCandidates(GrowthCampaign $campaign, int $limit): Collection
    {
        $delayMinutes = (int) ($campaign->config['delay_minutes'] ?? 45);
        $cutoff = now()->subMinutes(max(1, $delayMinutes));

        $rows = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_ADD_TO_CART)
            ->where('occurred_at', '<=', $cutoff)
            ->select('user_id', 'session_id', DB::raw('MAX(occurred_at) as latest_event_at'))
            ->groupBy('user_id', 'session_id')
            ->orderByDesc('latest_event_at')
            ->limit($limit)
            ->get();

        return $rows->filter(function ($row) use ($cutoff) {
            return ! AnalyticsEvent::query()
                ->where(fn (Builder $query) => $this->applyAudienceMatch($query, $row->user_id, $row->session_id))
                ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
                ->where('occurred_at', '>=', $cutoff)
                ->exists();
        })->map(function ($row) {
            $cartCount = (int) AnalyticsEvent::query()
                ->where(fn (Builder $query) => $this->applyAudienceMatch($query, $row->user_id, $row->session_id))
                ->where('event_type', AnalyticsEvent::EVENT_ADD_TO_CART)
                ->count();

            return [
                'user_id' => $row->user_id ? (int) $row->user_id : null,
                'session_id' => $row->session_id,
                'latest_event_at' => (string) $row->latest_event_at,
                'cart_count' => $cartCount,
                'reason' => 'cart_recovery',
            ];
        })->values();
    }

    protected function abandonedCheckoutCandidates(GrowthCampaign $campaign, int $limit): Collection
    {
        $delayMinutes = (int) ($campaign->config['delay_minutes'] ?? 30);
        $cutoff = now()->subMinutes(max(1, $delayMinutes));

        $rows = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_CHECKOUT_START)
            ->where('occurred_at', '<=', $cutoff)
            ->select('user_id', 'session_id', DB::raw('MAX(occurred_at) as latest_event_at'))
            ->groupBy('user_id', 'session_id')
            ->orderByDesc('latest_event_at')
            ->limit($limit)
            ->get();

        return $rows->filter(function ($row) use ($cutoff) {
            return ! AnalyticsEvent::query()
                ->where(fn (Builder $query) => $this->applyAudienceMatch($query, $row->user_id, $row->session_id))
                ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
                ->where('occurred_at', '>=', $cutoff)
                ->exists();
        })->map(fn ($row) => [
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'session_id' => $row->session_id,
            'latest_event_at' => (string) $row->latest_event_at,
            'reason' => 'abandoned_checkout',
        ])->values();
    }

    protected function repeatBuyerCandidates(GrowthCampaign $campaign, int $limit): Collection
    {
        $lookbackDays = (int) ($campaign->config['lookback_days'] ?? 90);
        $since = now()->subDays(max(7, $lookbackDays))->startOfDay();

        $rows = Order::query()
            ->whereNotNull('user_id')
            ->whereBetween(DB::raw('DATE(COALESCE(placed_at, created_at))'), [$since->toDateString(), now()->toDateString()])
            ->select('user_id', DB::raw('COUNT(*) as orders_count'), DB::raw('MAX(COALESCE(placed_at, created_at)) as latest_order_at'))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('latest_order_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'user_id' => (int) $row->user_id,
            'session_id' => null,
            'orders_count' => (int) $row->orders_count,
            'latest_event_at' => (string) $row->latest_order_at,
            'reason' => 'repeat_buyer',
        ])->values();
    }

    protected function highIntentCandidates(GrowthCampaign $campaign, int $limit): Collection
    {
        $viewThreshold = (int) ($campaign->config['view_threshold'] ?? 3);
        $lookbackHours = (int) ($campaign->config['lookback_hours'] ?? 72);
        $since = now()->subHours(max(1, $lookbackHours));

        $rows = AnalyticsEvent::query()
            ->where('occurred_at', '>=', $since)
            ->select(
                'user_id',
                'session_id',
                DB::raw("SUM(CASE WHEN event_type = 'view_product' THEN 1 ELSE 0 END) as view_count"),
                DB::raw("SUM(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as cart_count"),
                DB::raw('MAX(occurred_at) as latest_event_at')
            )
            ->groupBy('user_id', 'session_id')
            ->havingRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) >= ?', [AnalyticsEvent::EVENT_VIEW_PRODUCT, $viewThreshold])
            ->orderByDesc('latest_event_at')
            ->limit($limit)
            ->get();

        return $rows->filter(function ($row) use ($since) {
            return ! AnalyticsEvent::query()
                ->where(fn (Builder $query) => $this->applyAudienceMatch($query, $row->user_id, $row->session_id))
                ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
                ->where('occurred_at', '>=', $since)
                ->exists();
        })->map(fn ($row) => [
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'session_id' => $row->session_id,
            'view_count' => (int) $row->view_count,
            'cart_count' => (int) $row->cart_count,
            'latest_event_at' => (string) $row->latest_event_at,
            'reason' => 'high_intent_users',
        ])->values();
    }

    protected function atRiskWinbackCandidates(GrowthCampaign $campaign, int $limit): Collection
    {
        if (! $this->predictiveEnabled() || ! $this->winbackEnabled() || ! Schema::hasTable('growth_customer_scores')) {
            return collect();
        }

        $minimumRisk = (float) ($campaign->config['minimum_churn_risk'] ?? config('growth.at_risk_threshold', 70));
        $minimumDays = (int) ($campaign->config['minimum_days_since_order'] ?? 21);

        return GrowthCustomerScore::query()
            ->where('churn_risk_score', '>=', $minimumRisk)
            ->where('days_since_last_order', '>=', $minimumDays)
            ->where('orders_count', '>=', 2)
            ->where('next_best_campaign', 'at_risk_winback')
            ->with('user')
            ->orderByDesc('winback_priority_score')
            ->orderByDesc('churn_risk_score')
            ->limit($limit)
            ->get()
            ->map(fn (GrowthCustomerScore $score) => [
                'user_id' => $score->user_id,
                'session_id' => null,
                'orders_count' => (int) $score->orders_count,
                'ltv_score' => (float) $score->ltv_score,
                'churn_risk_score' => (float) $score->churn_risk_score,
                'days_since_last_order' => (int) $score->days_since_last_order,
                'latest_event_at' => optional($score->last_order_at)->toDateTimeString(),
                'reason' => 'at_risk_winback',
                'adaptive_offer_preference' => $score->adaptive_offer_preference,
                'winback_priority_score' => (float) $score->winback_priority_score,
                'winback_priority_band' => (string) $score->winback_priority_band,
                'recommended_discount_type' => $score->recommended_discount_type,
                'recommended_discount_value' => (float) $score->recommended_discount_value,
            ])->values();
    }

    protected function vipRetentionCandidates(GrowthCampaign $campaign, int $limit): Collection
    {
        if (! $this->predictiveEnabled() || ! Schema::hasTable('growth_customer_scores')) {
            return collect();
        }

        $minimumLtv = (float) ($campaign->config['minimum_ltv_score'] ?? config('growth.vip_threshold', 75));
        $minimumDays = (int) ($campaign->config['minimum_days_since_order'] ?? 21);
        $maximumDays = (int) ($campaign->config['maximum_days_since_order'] ?? 90);

        return GrowthCustomerScore::query()
            ->where('ltv_score', '>=', $minimumLtv)
            ->whereIn('retention_stage', ['vip', 'vip_needs_attention'])
            ->whereBetween('days_since_last_order', [$minimumDays, $maximumDays])
            ->with('user')
            ->orderByDesc('winback_priority_score')
            ->orderByDesc('ltv_score')
            ->limit($limit)
            ->get()
            ->map(fn (GrowthCustomerScore $score) => [
                'user_id' => $score->user_id,
                'session_id' => null,
                'orders_count' => (int) $score->orders_count,
                'ltv_score' => (float) $score->ltv_score,
                'churn_risk_score' => (float) $score->churn_risk_score,
                'days_since_last_order' => (int) $score->days_since_last_order,
                'latest_event_at' => optional($score->last_order_at)->toDateTimeString(),
                'reason' => 'vip_retention',
                'adaptive_offer_preference' => $score->adaptive_offer_preference,
                'winback_priority_score' => (float) $score->winback_priority_score,
                'winback_priority_band' => (string) $score->winback_priority_band,
                'recommended_discount_type' => $score->recommended_discount_type,
                'recommended_discount_value' => (float) $score->recommended_discount_value,
            ])->values();
    }

    protected function applyAudienceMatch(Builder $query, mixed $userId, mixed $sessionId): void
    {
        $query->where(function (Builder $nested) use ($userId, $sessionId) {
            if ($userId) {
                $nested->where('user_id', $userId);
            }

            if ($sessionId) {
                if ($userId) {
                    $nested->orWhere('session_id', $sessionId);
                } else {
                    $nested->where('session_id', $sessionId);
                }
            }
        });
    }

    protected function segmentMatches(?GrowthAudienceSegment $segment, array $candidate): bool
    {
        if (! $segment) {
            return true;
        }

        $filters = $segment->filters ?? [];

        if (! empty($filters['requires_user']) && empty($candidate['user_id'])) {
            return false;
        }

        if (! empty($filters['requires_session']) && empty($candidate['session_id'])) {
            return false;
        }

        if (isset($filters['minimum_orders']) && (int) ($candidate['orders_count'] ?? 0) < (int) $filters['minimum_orders']) {
            return false;
        }

        if (isset($filters['minimum_view_count']) && (int) ($candidate['view_count'] ?? 0) < (int) $filters['minimum_view_count']) {
            return false;
        }

        if (isset($filters['minimum_cart_count']) && (int) ($candidate['cart_count'] ?? 0) < (int) $filters['minimum_cart_count']) {
            return false;
        }

        if (isset($filters['minimum_ltv_score']) && (float) ($candidate['ltv_score'] ?? 0) < (float) $filters['minimum_ltv_score']) {
            return false;
        }

        if (isset($filters['minimum_churn_risk']) && (float) ($candidate['churn_risk_score'] ?? 0) < (float) $filters['minimum_churn_risk']) {
            return false;
        }

        if (isset($filters['minimum_days_since_order']) && (int) ($candidate['days_since_last_order'] ?? 0) < (int) $filters['minimum_days_since_order']) {
            return false;
        }

        return true;
    }

    protected function buildPerformance(): array
    {
        $triggeredCount = Schema::hasTable('growth_trigger_logs') ? GrowthTriggerLog::query()->count() : 0;
        $triggeredToday = Schema::hasTable('growth_trigger_logs') ? GrowthTriggerLog::query()->whereDate('triggered_at', today())->count() : 0;
        $sentCount = Schema::hasTable('growth_message_logs') ? GrowthMessageLog::query()->whereIn('status', ['sent', 'delivered', 'simulated'])->count() : 0;
        $simulatedEmailCount = Schema::hasTable('growth_message_logs') ? GrowthMessageLog::query()->where('channel', 'email_simulated')->count() : 0;
        $inAppCount = Schema::hasTable('growth_message_logs') ? GrowthMessageLog::query()->where('channel', 'in_app')->count() : 0;
        $deliveryCount = Schema::hasTable('growth_deliveries') ? GrowthDelivery::query()->count() : 0;
        $deliveryFailedCount = Schema::hasTable('growth_deliveries') ? GrowthDelivery::query()->where('status', 'failed')->count() : 0;
        $deliveryPendingCount = Schema::hasTable('growth_deliveries') ? GrowthDelivery::query()->where('status', 'pending')->count() : 0;
        $experimentCount = Schema::hasTable('growth_experiments') ? GrowthExperiment::query()->where('is_active', true)->count() : 0;
        $emailRealCount = Schema::hasTable('growth_deliveries') ? GrowthDelivery::query()->where('channel', 'email')->whereIn('status', ['sent', 'delivered'])->count() : 0;
        $scheduledCount = Schema::hasTable('growth_deliveries') ? GrowthDelivery::query()->where('status', 'scheduled')->count() : 0;
        $couponIssuedCount = Schema::hasTable('growth_coupon_issues') ? GrowthCouponIssue::query()->count() : 0;

        $attributedRevenue = Schema::hasTable('growth_attribution_touches') ? (float) GrowthAttributionTouch::query()->sum('revenue') : 0.0;
        $attributedOrders = Schema::hasTable('growth_attribution_touches') ? (int) GrowthAttributionTouch::query()->distinct('order_id')->count('order_id') : 0;
        $cohortRevenue90d = Schema::hasTable('growth_cohort_snapshots') ? (float) GrowthCohortSnapshot::query()->sum('revenue_90d') : 0.0;
        $predictiveCount = Schema::hasTable('growth_customer_scores') ? GrowthCustomerScore::query()->count() : 0;
        $atRiskCount = Schema::hasTable('growth_customer_scores') ? GrowthCustomerScore::query()->where('retention_stage', 'at_risk')->count() : 0;
        $vipCount = Schema::hasTable('growth_customer_scores') ? GrowthCustomerScore::query()->whereIn('retention_stage', ['vip', 'vip_needs_attention'])->count() : 0;
        $learningRows = Schema::hasTable('growth_offer_learning_snapshots') ? GrowthOfferLearningSnapshot::query()->count() : 0;

        return [
            'total_triggers' => (int) $triggeredCount,
            'triggers_today' => (int) $triggeredToday,
            'messages_sent' => (int) $sentCount,
            'email_simulated' => (int) $simulatedEmailCount,
            'email_real_sent' => (int) $emailRealCount,
            'in_app_sent' => (int) $inAppCount,
            'deliveries_total' => (int) $deliveryCount,
            'deliveries_failed' => (int) $deliveryFailedCount,
            'deliveries_pending' => (int) $deliveryPendingCount,
            'deliveries_scheduled' => (int) $scheduledCount,
            'active_campaigns' => Schema::hasTable('growth_campaigns') ? GrowthCampaign::query()->where('is_active', true)->count() : 0,
            'active_rules' => Schema::hasTable('growth_automation_rules') ? GrowthAutomationRule::query()->where('is_active', true)->count() : 0,
            'active_templates' => Schema::hasTable('growth_message_templates') ? GrowthMessageTemplate::query()->where('is_active', true)->count() : 0,
            'active_segments' => Schema::hasTable('growth_audience_segments') ? GrowthAudienceSegment::query()->where('is_active', true)->count() : 0,
            'active_experiments' => (int) $experimentCount,
            'coupons_issued' => (int) $couponIssuedCount,
            'attributed_revenue' => round($attributedRevenue, 2),
            'attributed_orders' => $attributedOrders,
            'cohort_revenue_90d' => round($cohortRevenue90d, 2),
            'predictive_customers' => (int) $predictiveCount,
            'predictive_at_risk' => (int) $atRiskCount,
            'predictive_vip' => (int) $vipCount,
            'adaptive_learning_rows' => (int) $learningRows,
        ];
    }

    public function dispatchMessage(GrowthCampaign $campaign, GrowthTriggerLog $triggerLog): ?GrowthDelivery
    {
        $user = $triggerLog->user_id ? User::query()->find($triggerLog->user_id) : null;
        [$experiment, $variant] = $this->resolveExperiment($campaign, $triggerLog, $user);
        $render = $this->renderMessage($campaign, $triggerLog, $user, $experiment, $variant);

        $delivery = app(GrowthDeliveryService::class)->queue($campaign, $triggerLog, $render, $experiment, $variant);

        $campaign->update([
            'stats' => array_merge($campaign->stats ?? [], [
                'last_delivery_at' => now()->toDateTimeString(),
                'message_count' => (int) (($campaign->stats['message_count'] ?? 0)) + 1,
            ]),
        ]);

        return $delivery;
    }

    protected function renderMessage(
        GrowthCampaign $campaign,
        GrowthTriggerLog $triggerLog,
        ?User $user = null,
        ?GrowthExperiment $experiment = null,
        ?array $variant = null
    ): array {
        $locale = $this->preferredLocale($user);
        $snapshot = $triggerLog->audience_snapshot ?? [];
        $timing = $this->smartTimingEnabled()
            ? app(GrowthTimingOptimizer::class)->recommend($campaign, $snapshot, $user)
            : ['scheduled_for' => now(), 'reason' => 'immediate', 'timing_score' => 100];

        $decision = $this->aiSelectionEnabled()
            ? app(GrowthOfferDecisionEngine::class)->decide($campaign, $snapshot, $user, $experiment, $variant)
            : ['offer_key' => 'default', 'offer_label' => __('Growth offer'), 'discount_type' => null, 'discount_value' => null, 'coupon_days_valid' => 3, 'reason' => 'disabled'];

        $issuedCoupon = null;
        $couponCode = Arr::get($variant, 'coupon_code', $campaign->coupon_code);

        if ($this->dynamicCouponsEnabled()) {
            $issuedCoupon = app(DynamicCouponService::class)->issueForDecision($campaign, $triggerLog, $decision, $user);
            $couponCode = $issuedCoupon['coupon_code'] ?? $couponCode;
        }

        $coupon = $couponCode ? Coupon::query()->where('code', $couponCode)->first() : null;
        $template = $campaign->default_template_key
            ? $this->resolveTemplate($campaign->default_template_key, $locale)
            : null;

        $subject = $this->localizedVariantValue($variant, 'subject_translations', $locale)
            ?: $template?->subject
            ?: $this->localizedValue($campaign->subject_translations, $locale)
            ?: ($campaign->subject ?: __('Growth campaign'));

        $baseMessage = $this->localizedVariantValue($variant, 'message_translations', $locale)
            ?: $template?->body
            ?: $this->localizedValue($campaign->message_translations, $locale)
            ?: ($campaign->message ?: __('A behavior-based growth campaign was triggered for this audience.'));

        if (! empty($issuedCoupon['coupon_code'])) {
            $offerLine = $locale === 'ar'
                ? ' استخدم الكود :coupon للحصول على :discount_value%.'
                : ' Use code :coupon to unlock :discount_value% off.';
            $baseMessage .= $offerLine;
        }

        $tokens = [
            ':customer_name' => $user?->name ?: __('Customer'),
            ':minutes' => (string) ($campaign->config['delay_minutes'] ?? 0),
            ':view_count' => (string) ($snapshot['view_count'] ?? 0),
            ':cart_count' => (string) ($snapshot['cart_count'] ?? 0),
            ':orders_count' => (string) ($snapshot['orders_count'] ?? 0),
            ':coupon' => $coupon?->code ?: ($couponCode ?: ''),
            ':store_name' => WebsiteSetting::getValue('store_name', config('app.name')),
            ':offer_label' => (string) ($issuedCoupon['offer_label'] ?? $decision['offer_label'] ?? ''),
            ':discount_value' => (string) ((int) ($issuedCoupon['discount_value'] ?? $decision['discount_value'] ?? 0)),
            ':ltv_score' => (string) round((float) ($snapshot['ltv_score'] ?? 0), 0),
            ':churn_risk' => (string) round((float) ($snapshot['churn_risk_score'] ?? 0), 0),
            ':days_since_last_order' => (string) ($snapshot['days_since_last_order'] ?? 0),
        ];

        return [
            'locale' => $locale,
            'subject' => strtr($subject, $tokens),
            'message' => strtr($baseMessage, $tokens),
            'coupon_code' => $coupon?->code ?: ($couponCode ?: null),
            'store_name' => $tokens[':store_name'],
            'template_key' => $template?->template_key ?: $campaign->default_template_key,
            'experiment_key' => $experiment?->experiment_key,
            'experiment_variant' => Arr::get($variant, 'key'),
            'scheduled_for' => $timing['scheduled_for']->toDateTimeString(),
            'timing_reason' => $timing['reason'] ?? null,
            'timing_score' => $timing['timing_score'] ?? null,
            'offer_key' => $decision['offer_key'] ?? null,
            'offer_label' => $issuedCoupon['offer_label'] ?? $decision['offer_label'] ?? null,
            'decision_reason' => $issuedCoupon['reason'] ?? $decision['reason'] ?? null,
            'discount_type' => $issuedCoupon['discount_type'] ?? $decision['discount_type'] ?? null,
            'discount_value' => $issuedCoupon['discount_value'] ?? $decision['discount_value'] ?? null,
            'adaptive_source' => Arr::get($variant, '_adaptive_source'),
            'adaptive_score' => Arr::get($variant, '_adaptive_score'),
        ];
    }

    protected function preferredLocale(?User $user = null): string
    {
        $locale = app()->getLocale();

        if (! empty(data_get($user, 'locale'))) {
            $locale = data_get($user, 'locale');
        }

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
    }

    protected function localizedValue(?array $translations, string $locale): ?string
    {
        if (! is_array($translations) || $translations === []) {
            return null;
        }

        return $translations[$locale] ?? $translations['en'] ?? $translations['ar'] ?? null;
    }

    protected function localizedVariantValue(?array $variant, string $key, string $locale): ?string
    {
        $translations = is_array($variant) ? Arr::get($variant, $key) : null;

        return is_array($translations) ? ($translations[$locale] ?? $translations['en'] ?? $translations['ar'] ?? null) : null;
    }

    protected function resolveTemplate(string $templateKey, string $locale): ?GrowthMessageTemplate
    {
        $query = GrowthMessageTemplate::query()
            ->where('template_key', $templateKey)
            ->where('is_active', true);

        if ($this->supportsLocalizedTemplates()) {
            $template = (clone $query)->where('locale', $locale)->first();

            if ($template) {
                return $template;
            }
        }

        return $query->orderByRaw("CASE WHEN locale = ? THEN 0 WHEN locale = 'en' THEN 1 WHEN locale = 'ar' THEN 2 ELSE 3 END", [$locale])->first();
    }

    protected function upsertTemplateSafely(array $template): void
    {
        $attributes = ['template_key' => $template['template_key']];
        if ($this->supportsLocalizedTemplates()) {
            $attributes['locale'] = $template['locale'];
        }

        try {
            GrowthMessageTemplate::query()->updateOrCreate($attributes, $template);

            return;
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }
        }

        $existing = GrowthMessageTemplate::query()
            ->where('template_key', $template['template_key'])
            ->when($this->supportsLocalizedTemplates(), fn ($query) => $query->where('locale', $template['locale']))
            ->first();

        if ($existing) {
            $existing->fill($template)->save();

            return;
        }

        if (! $this->supportsLocalizedTemplates()) {
            $legacyExisting = GrowthMessageTemplate::query()->where('template_key', $template['template_key'])->first();

            if ($legacyExisting) {
                if ($legacyExisting->locale === $template['locale']) {
                    $legacyExisting->fill($template)->save();
                }

                return;
            }
        }

        GrowthMessageTemplate::query()->create($template);
    }

    protected function supportsLocalizedTemplates(): bool
    {
        if (! Schema::hasTable('growth_message_templates')) {
            return true;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $indexes = collect(DB::select('SHOW INDEX FROM growth_message_templates'));

            $columns = $indexes->where('Key_name', 'growth_message_templates_template_key_locale_unique')
                ->sortBy('Seq_in_index')
                ->pluck('Column_name')
                ->values()
                ->all();

            if ($columns === ['template_key', 'locale']) {
                return true;
            }

            $legacy = $indexes->contains(fn ($index) => $index->Key_name === 'growth_message_templates_template_key_unique');

            return ! $legacy;
        }

        return true;
    }

    protected function isDuplicateKeyException(QueryException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'duplicate')
            || ((string) $exception->getCode() === '23000');
    }

    protected function resolveExperiment(GrowthCampaign $campaign, ?GrowthTriggerLog $triggerLog = null, ?User $user = null): array
    {
        if (! $this->experimentsEnabled() || ! Schema::hasTable('growth_experiments')) {
            return [null, null];
        }

        $experiment = $campaign->experiments()->where('is_active', true)->orderBy('priority')->first();

        if (! $experiment) {
            return [null, null];
        }

        $snapshot = $triggerLog?->audience_snapshot ?? [];
        return [$experiment, $this->pickVariant($experiment, $campaign, $snapshot, $user)];
    }

    protected function pickVariant(GrowthExperiment $experiment, ?GrowthCampaign $campaign = null, array $snapshot = [], ?User $user = null): ?array
    {
        $variants = collect($experiment->variants ?? [])->filter(fn ($variant) => ! empty($variant['key']))->values();

        if ($variants->isEmpty()) {
            return null;
        }

        $score = $user?->id && Schema::hasTable('growth_customer_scores')
            ? GrowthCustomerScore::query()->where('user_id', $user->id)->first()
            : null;

        if ($this->aiSelectionEnabled() && $this->adaptiveLearningEnabled()) {
            $adaptiveVariant = app(GrowthAdaptiveLearningService::class)->chooseVariant($experiment, $score);
            if ($adaptiveVariant) {
                return $adaptiveVariant;
            }
        }

        if (! $this->aiSelectionEnabled()) {
            $totalWeight = max(1, (int) $variants->sum(fn ($variant) => max(1, (int) ($variant['weight'] ?? 1))));
            $roll = random_int(1, $totalWeight);
            $cursor = 0;

            foreach ($variants as $variant) {
                $cursor += max(1, (int) ($variant['weight'] ?? 1));
                if ($roll <= $cursor) {
                    return $variant;
                }
            }

            return $variants->last();
        }

        $stats = collect($this->variantPerformance($experiment))->keyBy('key');

        $scored = $variants->map(function ($variant) use ($stats, $score) {
            $key = Arr::get($variant, 'key');
            $perf = $stats->get($key, []);
            $scoreValue = (int) ($variant['weight'] ?? 1);
            $scoreValue += (int) round((float) ($perf['conversion_rate'] ?? 0));
            $scoreValue += (int) round(((float) ($perf['revenue'] ?? 0)) / 100);
            if ($score) {
                $preferred = (string) ($score->adaptive_offer_preference ?: $score->offer_bias);
                $variantOfferKey = (string) Arr::get($variant, 'offer_key', $key);
                $discountType = (string) Arr::get($variant, 'discount_type', '');
                if ($preferred === 'free_shipping' && (str_contains($variantOfferKey, 'shipping') || $discountType === 'shipping')) {
                    $scoreValue += 25;
                }
                if ($preferred === 'discount_percentage' && in_array($discountType, ['percentage', 'percent'], true)) {
                    $scoreValue += 25;
                }
                if ($preferred === 'loyalty_reward' && str_contains($variantOfferKey, 'vip')) {
                    $scoreValue += 20;
                }
            }
            $variant['_score'] = max(1, $scoreValue);
            return $variant;
        })->sortByDesc('_score')->values();

        if (random_int(1, 100) <= 20) {
            return $scored->shuffle()->first();
        }

        return $scored->first();
    }

    protected function experimentPerformanceSummary(Collection $experiments): array
    {
        return $experiments->map(fn (GrowthExperiment $experiment) => [
            'experiment' => $experiment,
            'variants' => $this->variantPerformance($experiment),
        ])->all();
    }

    public function variantPerformance(GrowthExperiment $experiment): array
    {
        $windowHours = max(1, (int) config('growth.conversion_window_hours', 168));

        return collect($experiment->variants ?? [])->map(function ($variant) use ($experiment, $windowHours) {
            $variantKey = Arr::get($variant, 'key');

            $deliveries = GrowthDelivery::query()
                ->where('experiment_id', $experiment->id)
                ->where('experiment_variant', $variantKey)
                ->whereIn('status', ['sent', 'delivered', 'simulated'])
                ->get();

            $converted = 0;
            $revenue = 0.0;

            foreach ($deliveries as $delivery) {
                if (! $delivery->user_id || ! $delivery->sent_at) {
                    continue;
                }

                $orderQuery = Order::query()
                    ->where('user_id', $delivery->user_id)
                    ->where('created_at', '>=', $delivery->sent_at)
                    ->where('created_at', '<=', $delivery->sent_at->copy()->addHours($windowHours));

                if ($orderQuery->exists()) {
                    $converted++;
                    $revenue += (float) $orderQuery->sum('grand_total');
                }
            }

            $sent = max(0, $deliveries->count());

            return [
                'key' => $variantKey,
                'name' => Arr::get($variant, 'name', $variantKey),
                'weight' => (int) Arr::get($variant, 'weight', 1),
                'deliveries' => $sent,
                'converted' => $converted,
                'conversion_rate' => $sent > 0 ? round(($converted / $sent) * 100, 2) : 0.0,
                'revenue' => round($revenue, 2),
            ];
        })->all();
    }

    protected function defaultCampaigns(): array
    {
        $couponCode = Schema::hasTable('coupons') ? Coupon::query()->where('is_active', true)->orderByDesc('used_count')->value('code') : null;

        return [
            [
                'name' => __('Cart Recovery'),
                'campaign_key' => 'cart_recovery',
                'campaign_type' => 'cart_recovery',
                'trigger_event' => AnalyticsEvent::EVENT_ADD_TO_CART,
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'cart_abandoners')->value('id'),
                'subject' => __('Complete your cart'),
                'default_template_key' => 'cart_recovery',
                'message' => __('You left products in your cart a little while ago. Bring the visit back while intent is still warm.'),
                'subject_translations' => ['en' => 'Complete your cart', 'ar' => 'كمّل عربيتك'],
                'message_translations' => ['en' => 'You left products in your cart a little while ago. Use coupon :coupon and come back to finish your order.', 'ar' => 'أنت سيبت منتجات في العربة من شوية. استخدم كوبون :coupon وكمّل طلبك بسهولة.'],
                'coupon_code' => $couponCode,
                'config' => ['delay_minutes' => 45, 'cooldown_hours' => 24],
                'priority' => 10,
                'is_active' => true,
                'is_messaging_enabled' => false,
            ],
            [
                'name' => __('Abandoned Checkout'),
                'campaign_key' => 'abandoned_checkout',
                'campaign_type' => 'checkout_recovery',
                'trigger_event' => AnalyticsEvent::EVENT_CHECKOUT_START,
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'checkout_dropoffs')->value('id'),
                'subject' => __('Need help finishing checkout?'),
                'default_template_key' => 'checkout_rescue',
                'message' => __('Checkout started but did not finish. Use trust, delivery clarity, and a low-friction nudge instead of a heavy discount.'),
                'subject_translations' => ['en' => 'Need help finishing checkout?', 'ar' => 'محتاج مساعدة تكمل الشراء؟'],
                'message_translations' => ['en' => 'You started checkout but did not finish. Your items are still waiting for you.', 'ar' => 'بدأت خطوات الشراء لكن ماكملتش. منتجاتك لسه موجودة ومستنياك.'],
                'coupon_code' => null,
                'config' => ['delay_minutes' => 30, 'cooldown_hours' => 18],
                'priority' => 20,
                'is_active' => true,
                'is_messaging_enabled' => false,
            ],
            [
                'name' => __('Repeat Buyer Reactivation'),
                'campaign_key' => 'repeat_buyer',
                'campaign_type' => 'repeat_buyer',
                'trigger_event' => 'order_complete',
                'channel' => 'email',
                'audience_type' => 'user',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'repeat_buyers')->value('id'),
                'subject' => __('A new offer for your next order'),
                'default_template_key' => 'repeat_buyer',
                'message' => __('You already ordered with us before. This is the right moment to invite a repeat purchase with a focused CRM offer.'),
                'subject_translations' => ['en' => 'A new offer for your next order', 'ar' => 'عرض جديد لطلبك الجاي'],
                'message_translations' => ['en' => 'You already ordered :orders_count times. Here is a focused comeback offer for your next order.', 'ar' => 'أنت طلبت قبل كده :orders_count مرة. عندك عرض مخصص لطلبك الجاي.'],
                'coupon_code' => $couponCode,
                'config' => ['lookback_days' => 90, 'cooldown_hours' => 168],
                'priority' => 30,
                'is_active' => true,
                'is_messaging_enabled' => false,
            ],
            [
                'name' => __('High Intent Users'),
                'campaign_key' => 'high_intent_users',
                'campaign_type' => 'high_intent',
                'trigger_event' => AnalyticsEvent::EVENT_VIEW_PRODUCT,
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'high_intent_visitors')->value('id'),
                'subject' => __('Still thinking about it?'),
                'default_template_key' => 'high_intent',
                'message' => __('This visitor returned multiple times. Reinforce value, urgency, and product confidence while attention is still high.'),
                'subject_translations' => ['en' => 'Still thinking about it?', 'ar' => 'لسه بتفكر؟'],
                'message_translations' => ['en' => 'You viewed this product :view_count times. This is the right moment to help you decide.', 'ar' => 'أنت شفت المنتج ده :view_count مرات. ده الوقت المناسب نساعدك تكمل قرار الشراء.'],
                'coupon_code' => null,
                'config' => ['view_threshold' => 3, 'lookback_hours' => 72, 'cooldown_hours' => 12],
                'priority' => 40,
                'is_active' => true,
                'is_messaging_enabled' => false,
            ],
            [
                'name' => __('At-Risk Win-back'),
                'campaign_key' => 'at_risk_winback',
                'campaign_type' => 'winback',
                'trigger_event' => 'predictive_churn_risk',
                'channel' => 'email',
                'audience_type' => 'user',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'at_risk_customers')->value('id'),
                'subject' => __('We would love to see you again'),
                'default_template_key' => 'at_risk_winback',
                'message' => __('A predictive win-back flow for customers whose churn risk is rising.'),
                'subject_translations' => ['en' => 'We would love to see you again', 'ar' => 'وحشتنا، وعايزين نشوفك تاني'],
                'message_translations' => ['en' => 'It has been :days_since_last_order days since your last order. Here is a smart comeback offer from :store_name.', 'ar' => 'عدّى :days_since_last_order يوم من آخر طلب. جهزنا لك عرض رجوع ذكي من :store_name.'],
                'coupon_code' => $couponCode,
                'config' => ['minimum_churn_risk' => 70, 'minimum_days_since_order' => 21, 'cooldown_hours' => 168],
                'priority' => 50,
                'is_active' => true,
                'is_messaging_enabled' => false,
            ],
            [
                'name' => __('VIP Retention'),
                'campaign_key' => 'vip_retention',
                'campaign_type' => 'vip_retention',
                'trigger_event' => 'predictive_ltv_high',
                'channel' => 'email',
                'audience_type' => 'user',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'vip_customers')->value('id'),
                'subject' => __('An exclusive thank-you for your next order'),
                'default_template_key' => 'vip_retention',
                'message' => __('Protect high-value customers with a softer loyalty-driven message before they drift.'),
                'subject_translations' => ['en' => 'An exclusive thank-you for your next order', 'ar' => 'شكر خاص لطلبك الجاي'],
                'message_translations' => ['en' => 'Your value score is :ltv_score and you are one of our strongest customers. Enjoy an exclusive offer on your next order.', 'ar' => 'تقييم القيمة عندك :ltv_score وأنت من أهم عملائنا. عندك عرض حصري لطلبك الجاي.'],
                'coupon_code' => $couponCode,
                'config' => ['minimum_ltv_score' => 75, 'minimum_days_since_order' => 21, 'maximum_days_since_order' => 90, 'cooldown_hours' => 240],
                'priority' => 60,
                'is_active' => true,
                'is_messaging_enabled' => false,
            ],
        ];
    }

    protected function defaultRules(): array
    {
        return [
            [
                'name' => __('Cart recovery rule'),
                'rule_key' => 'cart_recovery',
                'trigger_type' => AnalyticsEvent::EVENT_ADD_TO_CART,
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'cart_abandoners')->value('id'),
                'subject' => __('Cart recovery'),
                'default_template_key' => 'cart_recovery',
                'message' => __('If the user adds to cart and does not purchase after the configured delay, make the audience eligible for recovery.'),
                'coupon_code' => null,
                'config' => ['delay_minutes' => 45, 'cooldown_hours' => 24],
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'name' => __('Checkout rescue rule'),
                'rule_key' => 'abandoned_checkout',
                'trigger_type' => AnalyticsEvent::EVENT_CHECKOUT_START,
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'checkout_dropoffs')->value('id'),
                'subject' => __('Checkout rescue'),
                'default_template_key' => 'checkout_rescue',
                'message' => __('If checkout starts but purchase does not complete after the delay window, queue a rescue opportunity.'),
                'coupon_code' => null,
                'config' => ['delay_minutes' => 30, 'cooldown_hours' => 18],
                'priority' => 20,
                'is_active' => true,
            ],
            [
                'name' => __('Repeat buyer retargeting rule'),
                'rule_key' => 'repeat_buyer',
                'trigger_type' => 'order_complete',
                'channel' => 'email',
                'audience_type' => 'user',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'repeat_buyers')->value('id'),
                'subject' => __('Repeat buyer reactivation'),
                'default_template_key' => 'repeat_buyer',
                'message' => __('If the customer has a repeat buying pattern within the lookback range, keep the segment eligible for CRM reactivation.'),
                'coupon_code' => null,
                'config' => ['lookback_days' => 90, 'cooldown_hours' => 168],
                'priority' => 30,
                'is_active' => true,
            ],
            [
                'name' => __('High intent rule'),
                'rule_key' => 'high_intent_users',
                'trigger_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT,
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'high_intent_visitors')->value('id'),
                'subject' => __('High intent'),
                'default_template_key' => 'high_intent',
                'message' => __('If view frequency crosses the threshold without purchase, treat the visitor as a high intent recovery opportunity.'),
                'coupon_code' => null,
                'config' => ['view_threshold' => 3, 'lookback_hours' => 72, 'cooldown_hours' => 12],
                'priority' => 40,
                'is_active' => true,
            ],
            [
                'name' => __('At-risk win-back rule'),
                'rule_key' => 'at_risk_winback',
                'trigger_type' => 'predictive_churn_risk',
                'channel' => 'email',
                'audience_type' => 'user',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'at_risk_customers')->value('id'),
                'subject' => __('At-risk win-back'),
                'default_template_key' => 'at_risk_winback',
                'message' => __('When churn risk crosses the threshold and the customer has gone quiet, queue a measured comeback campaign.'),
                'coupon_code' => null,
                'config' => ['minimum_churn_risk' => 70, 'minimum_days_since_order' => 21, 'cooldown_hours' => 168],
                'priority' => 50,
                'is_active' => true,
            ],
            [
                'name' => __('VIP retention rule'),
                'rule_key' => 'vip_retention',
                'trigger_type' => 'predictive_ltv_high',
                'channel' => 'email',
                'audience_type' => 'user',
                'segment_id' => GrowthAudienceSegment::query()->where('segment_key', 'vip_customers')->value('id'),
                'subject' => __('VIP retention'),
                'default_template_key' => 'vip_retention',
                'message' => __('If a high-value customer starts cooling off, protect the relationship before churn risk becomes expensive.'),
                'coupon_code' => null,
                'config' => ['minimum_ltv_score' => 75, 'minimum_days_since_order' => 21, 'maximum_days_since_order' => 90, 'cooldown_hours' => 240],
                'priority' => 60,
                'is_active' => true,
            ],
        ];
    }

    protected function defaultSegments(): array
    {
        return [
            [
                'name' => __('Cart abandoners'),
                'segment_key' => 'cart_abandoners',
                'audience_type' => 'user_or_session',
                'description' => __('Users or guest sessions that added to cart but did not complete purchase.'),
                'filters' => ['minimum_cart_count' => 1],
                'is_active' => true,
                'priority' => 10,
            ],
            [
                'name' => __('Checkout drop-offs'),
                'segment_key' => 'checkout_dropoffs',
                'audience_type' => 'user_or_session',
                'description' => __('Users who started checkout and need a softer rescue flow.'),
                'filters' => ['minimum_cart_count' => 0],
                'is_active' => true,
                'priority' => 20,
            ],
            [
                'name' => __('Repeat buyers'),
                'segment_key' => 'repeat_buyers',
                'audience_type' => 'user',
                'description' => __('Customers with at least two completed orders inside the lookback window.'),
                'filters' => ['requires_user' => true, 'minimum_orders' => 2],
                'is_active' => true,
                'priority' => 30,
            ],
            [
                'name' => __('High intent visitors'),
                'segment_key' => 'high_intent_visitors',
                'audience_type' => 'user_or_session',
                'description' => __('Visitors with repeated product views and no recent purchase.'),
                'filters' => ['minimum_view_count' => 3],
                'is_active' => true,
                'priority' => 40,
            ],
            [
                'name' => __('VIP customers'),
                'segment_key' => 'vip_customers',
                'audience_type' => 'user',
                'description' => __('High-value customers with strong lifetime value who deserve proactive retention.'),
                'filters' => ['requires_user' => true, 'minimum_orders' => 3, 'minimum_ltv_score' => 75, 'minimum_days_since_order' => 21],
                'is_active' => true,
                'priority' => 50,
            ],
            [
                'name' => __('At-risk customers'),
                'segment_key' => 'at_risk_customers',
                'audience_type' => 'user',
                'description' => __('Customers with rising churn risk that should enter win-back automation.'),
                'filters' => ['requires_user' => true, 'minimum_orders' => 2, 'minimum_churn_risk' => 70, 'minimum_days_since_order' => 21],
                'is_active' => true,
                'priority' => 60,
            ],
            [
                'name' => __('New customers'),
                'segment_key' => 'new_customers',
                'audience_type' => 'user',
                'description' => __('Fresh customers in the first purchase lifecycle that may need guided follow-up.'),
                'filters' => ['requires_user' => true, 'minimum_orders' => 1],
                'is_active' => true,
                'priority' => 70,
            ],
        ];
    }

    protected function defaultTemplates(): array
    {
        return [
            [
                'name' => __('Cart Recovery Arabic'),
                'template_key' => 'cart_recovery',
                'channel' => 'in_app',
                'locale' => 'ar',
                'subject' => 'كمّل عربيتك',
                'body' => 'يا :customer_name، لسه فيه منتجات في عربيتك. استخدم :coupon وكمّل الطلب من :store_name.',
                'tokens' => [':customer_name', ':coupon', ':store_name'],
                'is_active' => true,
                'priority' => 10,
            ],
            [
                'name' => __('Cart Recovery English'),
                'template_key' => 'cart_recovery',
                'channel' => 'in_app',
                'locale' => 'en',
                'subject' => 'Complete your cart',
                'body' => 'Hi :customer_name, your cart is still waiting. Use :coupon and finish your order with :store_name.',
                'tokens' => [':customer_name', ':coupon', ':store_name'],
                'is_active' => true,
                'priority' => 20,
            ],
            [
                'name' => __('Checkout Rescue Arabic'),
                'template_key' => 'checkout_rescue',
                'channel' => 'in_app',
                'locale' => 'ar',
                'subject' => 'لسه خطوة واحدة',
                'body' => 'بدأت الشراء ولسه باقي خطوة صغيرة. خلّي طلبك يكمل بسهولة من :store_name.',
                'tokens' => [':store_name'],
                'is_active' => true,
                'priority' => 30,
            ],
            [
                'name' => __('Checkout Rescue English'),
                'template_key' => 'checkout_rescue',
                'channel' => 'in_app',
                'locale' => 'en',
                'subject' => 'You are one step away',
                'body' => 'You started checkout and your items are still waiting at :store_name.',
                'tokens' => [':store_name'],
                'is_active' => true,
                'priority' => 40,
            ],
            [
                'name' => __('Repeat Buyer Arabic'),
                'template_key' => 'repeat_buyer',
                'channel' => 'email',
                'locale' => 'ar',
                'subject' => 'عرض جديد لطلبك الجاي',
                'body' => 'شكرًا لثقتك في :store_name. لأنك طلبت :orders_count مرات، جهزنا لك عرض مخصص :coupon.',
                'tokens' => [':store_name', ':orders_count', ':coupon'],
                'is_active' => true,
                'priority' => 50,
            ],
            [
                'name' => __('Repeat Buyer English'),
                'template_key' => 'repeat_buyer',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => 'A new offer for your next order',
                'body' => 'Thanks for shopping with :store_name. Since you already ordered :orders_count times, here is a comeback offer: :coupon.',
                'tokens' => [':store_name', ':orders_count', ':coupon'],
                'is_active' => true,
                'priority' => 60,
            ],
            [
                'name' => __('High Intent Arabic'),
                'template_key' => 'high_intent',
                'channel' => 'in_app',
                'locale' => 'ar',
                'subject' => 'لسه بتفكر؟',
                'body' => 'أنت راجعت المنتج :view_count مرات. لو محتاج مساعدة أو عرض مناسب، إحنا جاهزين.',
                'tokens' => [':view_count'],
                'is_active' => true,
                'priority' => 70,
            ],
            [
                'name' => __('High Intent English'),
                'template_key' => 'high_intent',
                'channel' => 'in_app',
                'locale' => 'en',
                'subject' => 'Still thinking about it?',
                'body' => 'You looked at this product :view_count times. This is a great moment to help you convert.',
                'tokens' => [':view_count'],
                'is_active' => true,
                'priority' => 80,
            ],
            [
                'name' => __('At-Risk Win-back Arabic'),
                'template_key' => 'at_risk_winback',
                'channel' => 'email',
                'locale' => 'ar',
                'subject' => 'وحشتنا، وعايزين نشوفك تاني',
                'body' => 'يا :customer_name، عدّى :days_since_last_order يوم من آخر طلب. استخدم :coupon وارجع لـ :store_name بعرض رجوع مناسب.',
                'tokens' => [':customer_name', ':days_since_last_order', ':coupon', ':store_name'],
                'is_active' => true,
                'priority' => 90,
            ],
            [
                'name' => __('At-Risk Win-back English'),
                'template_key' => 'at_risk_winback',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => 'We would love to see you again',
                'body' => 'Hi :customer_name, it has been :days_since_last_order days since your last order. Use :coupon and come back to :store_name with a smarter comeback offer.',
                'tokens' => [':customer_name', ':days_since_last_order', ':coupon', ':store_name'],
                'is_active' => true,
                'priority' => 100,
            ],
            [
                'name' => __('VIP Retention Arabic'),
                'template_key' => 'vip_retention',
                'channel' => 'email',
                'locale' => 'ar',
                'subject' => 'شكر خاص لطلبك الجاي',
                'body' => 'أنت من أهم عملائنا وتقييم القيمة عندك :ltv_score. استخدم :coupon في :store_name كعرض تقدير خاص.',
                'tokens' => [':ltv_score', ':coupon', ':store_name'],
                'is_active' => true,
                'priority' => 110,
            ],
            [
                'name' => __('VIP Retention English'),
                'template_key' => 'vip_retention',
                'channel' => 'email',
                'locale' => 'en',
                'subject' => 'An exclusive thank-you for your next order',
                'body' => 'Your value score is :ltv_score. Use :coupon at :store_name as a thank-you reward for staying with us.',
                'tokens' => [':ltv_score', ':coupon', ':store_name'],
                'is_active' => true,
                'priority' => 120,
            ],
        ];
    }

    protected function defaultExperiments(): array
    {
        $couponCode = Schema::hasTable('coupons') ? Coupon::query()->where('is_active', true)->orderByDesc('used_count')->value('code') : null;

        return [
            [
                'campaign_id' => GrowthCampaign::query()->where('campaign_key', 'cart_recovery')->value('id'),
                'name' => __('Cart Recovery Offer Test'),
                'experiment_key' => 'cart_recovery_offer_test',
                'description' => __('Compare urgency-led copy vs discount-led copy for cart recovery.'),
                'variants' => [
                    [
                        'key' => 'A',
                        'name' => 'Urgency',
                        'weight' => 50,
                        'coupon_code' => $couponCode,
                        'subject_translations' => ['ar' => 'كمّل عربيتك قبل ما الفرصة تخلص', 'en' => 'Complete your cart while the intent is still high'],
                        'message_translations' => ['ar' => 'لسه منتجاتك موجودة. استخدم :coupon وارجع كمّل طلبك من :store_name.', 'en' => 'Your cart is still ready. Use :coupon and come back to finish your order at :store_name.'],
                    ],
                    [
                        'key' => 'B',
                        'name' => 'Benefit',
                        'weight' => 50,
                        'coupon_code' => $couponCode,
                        'subject_translations' => ['ar' => 'عندك عرض يساعدك تكمل الطلب', 'en' => 'A small offer to help you complete the order'],
                        'message_translations' => ['ar' => 'جهزنا لك دفعة بسيطة ترجعك للشراء. كوبونك :coupon من :store_name.', 'en' => 'Here is a focused nudge to bring you back. Your coupon is :coupon from :store_name.'],
                    ],
                ],
                'stats' => [],
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'campaign_id' => GrowthCampaign::query()->where('campaign_key', 'repeat_buyer')->value('id'),
                'name' => __('Repeat Buyer Offer Test'),
                'experiment_key' => 'repeat_buyer_offer_test',
                'description' => __('Compare a pure discount vs a loyalty-style thank-you message for repeat buyers.'),
                'variants' => [
                    [
                        'key' => 'A',
                        'name' => 'Discount',
                        'weight' => 50,
                        'coupon_code' => $couponCode,
                        'subject_translations' => ['ar' => 'خصم لطلبك الجاي', 'en' => 'A discount for your next order'],
                        'message_translations' => ['ar' => 'علشان طلبت :orders_count مرات، عندك عرض :coupon على الطلب الجاي.', 'en' => 'Because you already ordered :orders_count times, you now have :coupon for the next order.'],
                    ],
                    [
                        'key' => 'B',
                        'name' => 'Loyalty',
                        'weight' => 50,
                        'coupon_code' => $couponCode,
                        'subject_translations' => ['ar' => 'شكرًا لثقتك بينا', 'en' => 'Thanks for being a repeat customer'],
                        'message_translations' => ['ar' => 'تقديرًا لولائك، جهزنا لك هدية بسيطة على الطلب الجاي: :coupon.', 'en' => 'As a thank-you for your loyalty, here is a comeback offer for your next order: :coupon.'],
                    ],
                ],
                'stats' => [],
                'priority' => 20,
                'is_active' => true,
            ],
        ];
    }
}
