<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrowthAudienceSegment;
use App\Models\GrowthAutomationRule;
use App\Models\GrowthCampaign;
use App\Models\GrowthDelivery;
use App\Models\GrowthExperiment;
use App\Models\GrowthMessageTemplate;
use App\Services\Growth\GrowthCampaignService;
use App\Services\Growth\GrowthValidationDemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GrowthController extends Controller
{
    public function __construct(protected GrowthCampaignService $growthCampaignService)
    {
        $this->growthCampaignService->ensureDefaults();
    }

    public function index(): View
    {
        return $this->renderModulePage('overview');
    }

    public function content(): View
    {
        return $this->renderModulePage('content');
    }

    public function operations(): View
    {
        return $this->renderModulePage('operations');
    }

    public function insights(): View
    {
        return $this->renderModulePage('insights');
    }

    protected function renderModulePage(string $page): View
    {
        $snapshot = $this->growthCampaignService->dashboardSnapshot();
        $settings = $snapshot['settings'] ?? [];

        $viewPage = $page === 'overview' ? 'index' : $page;

        return view('admin.growth.'.$viewPage, [
            'snapshot' => $snapshot,
            'pageMeta' => $this->pageMeta($page),
            'engineOn' => ! empty($settings['engine_enabled']),
            'messagingOn' => ! empty($settings['messaging_enabled']),
            'realEmailOn' => ! empty($settings['real_email_enabled']),
            'experimentsOn' => ! empty($settings['experiments_enabled']),
            'campaigns' => collect($snapshot['campaigns'] ?? []),
            'rules' => collect($snapshot['rules'] ?? []),
            'templates' => collect($snapshot['templates'] ?? []),
            'segments' => collect($snapshot['segments'] ?? []),
            'experiments' => collect($snapshot['experiments'] ?? []),
            'deliveries' => collect($snapshot['deliveries'] ?? []),
            'triggerLogs' => collect($snapshot['trigger_logs'] ?? []),
            'messageLogs' => collect($snapshot['message_logs'] ?? []),
            'attributionSummary' => $snapshot['attribution_summary'] ?? [],
            'attributionBreakdown' => collect($snapshot['attribution_breakdown'] ?? []),
            'cohortSummary' => $snapshot['cohort_summary'] ?? [],
            'cohortRows' => collect($snapshot['cohort_rows'] ?? []),
            'predictiveSummary' => $snapshot['predictive_summary'] ?? [],
            'predictiveRows' => collect($snapshot['predictive_rows'] ?? []),
            'adaptiveLearningRows' => collect($snapshot['adaptive_learning_rows'] ?? []),
            'experimentPerformance' => collect($snapshot['experiment_performance'] ?? []),
            'quickLinks' => [
                'overview' => route('admin.growth.index'),
                'content' => route('admin.growth.content'),
                'operations' => route('admin.growth.operations'),
                'insights' => route('admin.growth.insights'),
            ],
            'deliveryStatusLabels' => [
                'pending' => __('Pending'),
                'sent' => __('Sent'),
                'delivered' => __('Delivered'),
                'failed' => __('Failed'),
                'simulated' => __('Simulated'),
            ],
        ]);
    }

    protected function pageMeta(string $page): array
    {
        $pages = [
            'overview' => [
                'title' => __('Growth Overview'),
                'heading' => __('Growth overview'),
                'description' => __('Start with growth health, quick controls, and the fastest next actions before opening the heavier workspaces.'),
            ],
            'content' => [
                'title' => __('Growth Content & Journeys'),
                'heading' => __('Content, campaigns, and journeys'),
                'description' => __('Manage campaigns, rules, templates, segments, and experiments in one focused creation workspace.'),
            ],
            'operations' => [
                'title' => __('Growth Operations'),
                'heading' => __('Operations and delivery recovery'),
                'description' => __('Run the engine, seed demo data, review deliveries, and inspect trigger and message logs without the rest of the growth page getting in the way.'),
            ],
            'insights' => [
                'title' => __('Growth Insights'),
                'heading' => __('Insights, attribution, and predictions'),
                'description' => __('Review revenue impact, retention, predictive risk, and adaptive learning from a dedicated analytics workspace.'),
            ],
        ];

        return array_merge($pages[$page] ?? $pages['overview'], ['key' => $page]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'growth_delivery_provider' => ['nullable', Rule::in(config('growth.supported_email_providers', ['smtp']))],
        ]);

        $this->growthCampaignService->updateSettings(array_merge($request->all(), $validated));

        return back()->with('success', __('Growth workspace settings were saved successfully.'));
    }

    public function toggleCampaign(GrowthCampaign $campaign): RedirectResponse
    {
        $this->growthCampaignService->toggleCampaign($campaign);

        return back()->with('success', __('Campaign status was updated successfully.'));
    }

    public function toggleCampaignMessaging(GrowthCampaign $campaign): RedirectResponse
    {
        $this->growthCampaignService->toggleCampaignMessaging($campaign);

        return back()->with('success', __('Campaign messaging status was updated successfully.'));
    }

    public function toggleRule(GrowthAutomationRule $rule): RedirectResponse
    {
        $this->growthCampaignService->toggleRule($rule);

        return back()->with('success', __('Automation rule status was updated successfully.'));
    }

    public function toggleExperiment(GrowthExperiment $experiment): RedirectResponse
    {
        $this->growthCampaignService->toggleExperiment($experiment);

        return back()->with('success', __('Experiment status was updated successfully.'));
    }

    public function retryDelivery(GrowthDelivery $delivery): RedirectResponse
    {
        $this->growthCampaignService->retryDelivery($delivery);

        return back()->with('success', __('Delivery retry was queued successfully.'));
    }

    public function runNow(Request $request): RedirectResponse
    {
        $campaign = $request->filled('campaign_id')
            ? GrowthCampaign::query()->findOrFail((int) $request->input('campaign_id'))
            : null;

        $result = $this->growthCampaignService->runNow($campaign);

        return back()->with('success', __('Growth engine run completed. Processed: :processed | Triggered: :triggered | Messages: :messages | Scheduled: :scheduled | Due processed: :due | Skipped: :skipped', $result));
    }

    public function seedValidationDemo(GrowthValidationDemoService $growthValidationDemoService): RedirectResponse
    {
        $result = $growthValidationDemoService->seed();

        return back()->with('success', __('Growth validation demo data was added successfully. Users: :users | Orders: :orders | Events: :events | Demo products: :products', [
            'users' => $result['users'] ?? 0,
            'orders' => $result['orders'] ?? 0,
            'events' => $result['events'] ?? 0,
            'products' => $result['products'] ?? 0,
        ]));
    }

    public function clearValidationDemo(GrowthValidationDemoService $growthValidationDemoService): RedirectResponse
    {
        $result = $growthValidationDemoService->clear(true);

        return back()->with('success', __('Growth validation demo data was cleared successfully. Users: :users | Orders: :orders | Events: :events | Products removed: :products', [
            'users' => $result['users'] ?? 0,
            'orders' => $result['orders'] ?? 0,
            'events' => $result['events'] ?? 0,
            'products' => $result['products_removed'] ?? 0,
        ]));
    }

    public function createCampaign(): View
    {
        return view('admin.growth.campaign-form', [
            'campaign' => new GrowthCampaign([
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'priority' => 100,
                'is_active' => true,
                'is_messaging_enabled' => false,
                'config' => [],
                'subject_translations' => ['ar' => null, 'en' => null],
                'message_translations' => ['ar' => null, 'en' => null],
            ]),
            'segments' => GrowthAudienceSegment::query()->orderBy('priority')->orderBy('name')->get(),
            'templateGroups' => $this->templateGroups(),
        ]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        GrowthCampaign::query()->create($this->validateCampaign($request));

        return redirect()->route('admin.growth.index')->with('success', __('Campaign was created successfully.'));
    }

    public function editCampaign(GrowthCampaign $campaign): View
    {
        return view('admin.growth.campaign-form', [
            'campaign' => $campaign,
            'segments' => GrowthAudienceSegment::query()->orderBy('priority')->orderBy('name')->get(),
            'templateGroups' => $this->templateGroups(),
        ]);
    }

    public function updateCampaign(Request $request, GrowthCampaign $campaign): RedirectResponse
    {
        $campaign->update($this->validateCampaign($request, $campaign));

        return redirect()->route('admin.growth.index')->with('success', __('Campaign was updated successfully.'));
    }

    public function destroyCampaign(GrowthCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return back()->with('success', __('Campaign was removed successfully.'));
    }

    public function createRule(): View
    {
        return view('admin.growth.rule-form', [
            'rule' => new GrowthAutomationRule([
                'channel' => 'in_app',
                'audience_type' => 'user_or_session',
                'priority' => 100,
                'is_active' => true,
                'config' => [],
            ]),
            'segments' => GrowthAudienceSegment::query()->orderBy('priority')->orderBy('name')->get(),
            'templateGroups' => $this->templateGroups(),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        GrowthAutomationRule::query()->create($this->validateRule($request));

        return redirect()->route('admin.growth.index')->with('success', __('Automation rule was created successfully.'));
    }

    public function editRule(GrowthAutomationRule $rule): View
    {
        return view('admin.growth.rule-form', [
            'rule' => $rule,
            'segments' => GrowthAudienceSegment::query()->orderBy('priority')->orderBy('name')->get(),
            'templateGroups' => $this->templateGroups(),
        ]);
    }

    public function updateRule(Request $request, GrowthAutomationRule $rule): RedirectResponse
    {
        $rule->update($this->validateRule($request, $rule));

        return redirect()->route('admin.growth.index')->with('success', __('Automation rule was updated successfully.'));
    }

    public function destroyRule(GrowthAutomationRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', __('Automation rule was removed successfully.'));
    }

    public function createTemplate(): View
    {
        return view('admin.growth.template-form', [
            'template' => new GrowthMessageTemplate([
                'channel' => 'in_app',
                'locale' => 'ar',
                'priority' => 100,
                'is_active' => true,
            ]),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        GrowthMessageTemplate::query()->create($this->validateTemplate($request));

        return redirect()->route('admin.growth.index')->with('success', __('Message template was created successfully.'));
    }

    public function editTemplate(GrowthMessageTemplate $template): View
    {
        return view('admin.growth.template-form', [
            'template' => $template,
        ]);
    }

    public function updateTemplate(Request $request, GrowthMessageTemplate $template): RedirectResponse
    {
        $template->update($this->validateTemplate($request, $template));

        return redirect()->route('admin.growth.index')->with('success', __('Message template was updated successfully.'));
    }

    public function destroyTemplate(GrowthMessageTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', __('Message template was removed successfully.'));
    }

    public function createSegment(): View
    {
        return view('admin.growth.segment-form', [
            'segment' => new GrowthAudienceSegment([
                'audience_type' => 'user_or_session',
                'priority' => 100,
                'is_active' => true,
                'filters' => [],
            ]),
        ]);
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        GrowthAudienceSegment::query()->create($this->validateSegment($request));

        return redirect()->route('admin.growth.index')->with('success', __('Audience segment was created successfully.'));
    }

    public function editSegment(GrowthAudienceSegment $segment): View
    {
        return view('admin.growth.segment-form', [
            'segment' => $segment,
        ]);
    }

    public function updateSegment(Request $request, GrowthAudienceSegment $segment): RedirectResponse
    {
        $segment->update($this->validateSegment($request, $segment));

        return redirect()->route('admin.growth.index')->with('success', __('Audience segment was updated successfully.'));
    }

    public function destroySegment(GrowthAudienceSegment $segment): RedirectResponse
    {
        if ($segment->campaigns()->exists() || $segment->rules()->exists()) {
            return back()->with('error', __('This segment is still linked to campaigns or rules. Reassign it before removing the segment.'));
        }

        $segment->delete();

        return back()->with('success', __('Audience segment was removed successfully.'));
    }

    public function createExperiment(): View
    {
        return view('admin.growth.experiment-form', [
            'experiment' => new GrowthExperiment([
                'priority' => 100,
                'is_active' => true,
                'variants' => [
                    ['key' => 'A', 'name' => 'Variant A', 'weight' => 50, 'coupon_code' => null, 'subject_translations' => ['ar' => null, 'en' => null], 'message_translations' => ['ar' => null, 'en' => null]],
                    ['key' => 'B', 'name' => 'Variant B', 'weight' => 50, 'coupon_code' => null, 'subject_translations' => ['ar' => null, 'en' => null], 'message_translations' => ['ar' => null, 'en' => null]],
                ],
            ]),
            'campaigns' => GrowthCampaign::query()->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function storeExperiment(Request $request): RedirectResponse
    {
        GrowthExperiment::query()->create($this->validateExperiment($request));

        return redirect()->route('admin.growth.index')->with('success', __('Experiment was created successfully.'));
    }

    public function editExperiment(GrowthExperiment $experiment): View
    {
        return view('admin.growth.experiment-form', [
            'experiment' => $experiment,
            'campaigns' => GrowthCampaign::query()->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function updateExperiment(Request $request, GrowthExperiment $experiment): RedirectResponse
    {
        $experiment->update($this->validateExperiment($request, $experiment));

        return redirect()->route('admin.growth.index')->with('success', __('Experiment was updated successfully.'));
    }

    public function destroyExperiment(GrowthExperiment $experiment): RedirectResponse
    {
        $experiment->delete();

        return back()->with('success', __('Experiment was removed successfully.'));
    }

    protected function validateCampaign(Request $request, ?GrowthCampaign $campaign = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'campaign_key' => ['nullable', 'string', 'max:120', Rule::unique('growth_campaigns', 'campaign_key')->ignore($campaign?->id)],
            'campaign_type' => ['required', 'string', 'max:120'],
            'trigger_event' => ['nullable', 'string', 'max:120'],
            'channel' => ['required', Rule::in(['in_app', 'email'])],
            'audience_type' => ['required', Rule::in(['user', 'session', 'user_or_session'])],
            'segment_id' => ['nullable', 'integer', 'exists:growth_audience_segments,id'],
            'default_template_key' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'delay_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'cooldown_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'lookback_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'lookback_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'view_threshold' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'minimum_ltv_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_churn_risk' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_days_since_order' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'maximum_days_since_order' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['nullable', 'boolean'],
            'is_messaging_enabled' => ['nullable', 'boolean'],
            'subject_ar' => ['nullable', 'string', 'max:255'],
            'subject_en' => ['nullable', 'string', 'max:255'],
            'message_ar' => ['nullable', 'string'],
            'message_en' => ['nullable', 'string'],
        ]);

        return [
            'name' => $validated['name'],
            'campaign_key' => $validated['campaign_key'] ?: Str::slug($validated['name'], '_'),
            'campaign_type' => $validated['campaign_type'],
            'trigger_event' => $validated['trigger_event'] ?? null,
            'channel' => $validated['channel'],
            'audience_type' => $validated['audience_type'],
            'segment_id' => $validated['segment_id'] ?? null,
            'default_template_key' => $validated['default_template_key'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'] ?? null,
            'coupon_code' => $validated['coupon_code'] ?? null,
            'priority' => (int) $validated['priority'],
            'config' => $this->configFromValidated($validated),
            'is_active' => $request->boolean('is_active'),
            'is_messaging_enabled' => $request->boolean('is_messaging_enabled'),
            'subject_translations' => ['ar' => $validated['subject_ar'] ?? null, 'en' => $validated['subject_en'] ?? null],
            'message_translations' => ['ar' => $validated['message_ar'] ?? null, 'en' => $validated['message_en'] ?? null],
        ];
    }

    protected function validateRule(Request $request, ?GrowthAutomationRule $rule = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rule_key' => ['nullable', 'string', 'max:120', Rule::unique('growth_automation_rules', 'rule_key')->ignore($rule?->id)],
            'trigger_type' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::in(['in_app', 'email'])],
            'audience_type' => ['required', Rule::in(['user', 'session', 'user_or_session'])],
            'segment_id' => ['nullable', 'integer', 'exists:growth_audience_segments,id'],
            'default_template_key' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'delay_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'cooldown_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'lookback_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'lookback_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'view_threshold' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'minimum_ltv_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_churn_risk' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_days_since_order' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'maximum_days_since_order' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $validated['name'],
            'rule_key' => $validated['rule_key'] ?: Str::slug($validated['name'], '_'),
            'trigger_type' => $validated['trigger_type'],
            'channel' => $validated['channel'],
            'audience_type' => $validated['audience_type'],
            'segment_id' => $validated['segment_id'] ?? null,
            'default_template_key' => $validated['default_template_key'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'] ?? null,
            'coupon_code' => $validated['coupon_code'] ?? null,
            'priority' => (int) $validated['priority'],
            'config' => $this->configFromValidated($validated),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    protected function validateTemplate(Request $request, ?GrowthMessageTemplate $template = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_key' => [
                'required',
                'string',
                'max:120',
                Rule::unique('growth_message_templates', 'template_key')
                    ->where(fn ($query) => $query->where('locale', $request->input('locale')))
                    ->ignore($template?->id),
            ],
            'channel' => ['required', Rule::in(['in_app', 'email'])],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'tokens_text' => ['nullable', 'string'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $tokens = collect(explode(',', (string) ($validated['tokens_text'] ?? '')))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $validated['name'],
            'template_key' => $validated['template_key'],
            'channel' => $validated['channel'],
            'locale' => $validated['locale'],
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'tokens' => $tokens,
            'priority' => (int) $validated['priority'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    protected function validateSegment(Request $request, ?GrowthAudienceSegment $segment = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'segment_key' => ['nullable', 'string', 'max:120', Rule::unique('growth_audience_segments', 'segment_key')->ignore($segment?->id)],
            'audience_type' => ['required', Rule::in(['user', 'session', 'user_or_session'])],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'minimum_orders' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'minimum_view_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'minimum_cart_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'minimum_ltv_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_churn_risk' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_days_since_order' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'requires_user' => ['nullable', 'boolean'],
            'requires_session' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $validated['name'],
            'segment_key' => $validated['segment_key'] ?: Str::slug($validated['name'], '_'),
            'audience_type' => $validated['audience_type'],
            'description' => $validated['description'] ?? null,
            'priority' => (int) $validated['priority'],
            'filters' => collect([
                'minimum_orders' => $validated['minimum_orders'] ?? null,
                'minimum_view_count' => $validated['minimum_view_count'] ?? null,
                'minimum_cart_count' => $validated['minimum_cart_count'] ?? null,
                'minimum_ltv_score' => $validated['minimum_ltv_score'] ?? null,
                'minimum_churn_risk' => $validated['minimum_churn_risk'] ?? null,
                'minimum_days_since_order' => $validated['minimum_days_since_order'] ?? null,
                'requires_user' => $request->boolean('requires_user'),
                'requires_session' => $request->boolean('requires_session'),
            ])->filter(fn ($value) => $value !== null && $value !== '' && $value !== false)->all(),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    protected function validateExperiment(Request $request, ?GrowthExperiment $experiment = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'experiment_key' => ['nullable', 'string', 'max:120', Rule::unique('growth_experiments', 'experiment_key')->ignore($experiment?->id)],
            'campaign_id' => ['nullable', 'integer', 'exists:growth_campaigns,id'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'variant_keys' => ['required', 'array', 'min:2'],
            'variant_keys.*' => ['required', 'string', 'max:20'],
            'variant_names' => ['required', 'array', 'min:2'],
            'variant_names.*' => ['required', 'string', 'max:120'],
            'variant_weights' => ['nullable', 'array'],
            'variant_coupon_codes' => ['nullable', 'array'],
            'variant_subject_ar' => ['nullable', 'array'],
            'variant_subject_en' => ['nullable', 'array'],
            'variant_message_ar' => ['nullable', 'array'],
            'variant_message_en' => ['nullable', 'array'],
        ]);

        $variants = collect($validated['variant_keys'])->values()->map(function ($key, $index) use ($request) {
            return [
                'key' => strtoupper(trim((string) $key)),
                'name' => trim((string) Arr::get($request->input('variant_names', []), $index, 'Variant '.($index + 1))),
                'weight' => max(1, (int) Arr::get($request->input('variant_weights', []), $index, 1)),
                'coupon_code' => Arr::get($request->input('variant_coupon_codes', []), $index),
                'subject_translations' => [
                    'ar' => Arr::get($request->input('variant_subject_ar', []), $index),
                    'en' => Arr::get($request->input('variant_subject_en', []), $index),
                ],
                'message_translations' => [
                    'ar' => Arr::get($request->input('variant_message_ar', []), $index),
                    'en' => Arr::get($request->input('variant_message_en', []), $index),
                ],
            ];
        })->filter(fn ($variant) => $variant['key'] !== '' && $variant['name'] !== '')->values()->all();

        return [
            'name' => $validated['name'],
            'experiment_key' => $validated['experiment_key'] ?: Str::slug($validated['name'], '_'),
            'campaign_id' => $validated['campaign_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'variants' => $variants,
            'priority' => (int) $validated['priority'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    protected function configFromValidated(array $validated): array
    {
        return collect([
            'delay_minutes' => $validated['delay_minutes'] ?? null,
            'cooldown_hours' => $validated['cooldown_hours'] ?? null,
            'lookback_days' => $validated['lookback_days'] ?? null,
            'lookback_hours' => $validated['lookback_hours'] ?? null,
            'view_threshold' => $validated['view_threshold'] ?? null,
            'minimum_ltv_score' => $validated['minimum_ltv_score'] ?? null,
            'minimum_churn_risk' => $validated['minimum_churn_risk'] ?? null,
            'minimum_days_since_order' => $validated['minimum_days_since_order'] ?? null,
            'maximum_days_since_order' => $validated['maximum_days_since_order'] ?? null,
        ])->filter(fn ($value) => $value !== null && $value !== '')->all();
    }

    protected function templateGroups(): array
    {
        return GrowthMessageTemplate::query()
            ->orderBy('template_key')
            ->orderBy('locale')
            ->get()
            ->groupBy('template_key')
            ->map(fn ($group) => $group->pluck('locale')->implode(' / '))
            ->all();
    }
}
