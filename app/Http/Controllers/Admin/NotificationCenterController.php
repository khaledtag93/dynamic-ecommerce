<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\NotificationAutomationRule;
use App\Models\NotificationDispatchLog;
use App\Models\Order;
use App\Models\WhatsAppLog;
use App\Services\Commerce\AdminActivityLogService;
use App\Services\Commerce\NotificationAutomationService;
use App\Services\Commerce\NotificationTemplateService;
use App\Services\Commerce\QueueMonitoringService;
use App\Services\Commerce\NotificationActionSafetyService;
use App\Services\Commerce\OrderNotificationService;
use App\Services\Commerce\QueueRecoveryService;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class NotificationCenterController extends Controller
{
    public function __construct(
        protected StoreSettingsService $settingsService,
        protected OrderNotificationService $orderNotificationService,
        protected WhatsAppServiceInterface $whatsAppService,
        protected AdminActivityLogService $adminActivityLogService,
        protected NotificationTemplateService $notificationTemplateService,
        protected QueueMonitoringService $queueMonitoringService,
        protected NotificationAutomationService $notificationAutomationService,
        protected QueueRecoveryService $queueRecoveryService,
        protected NotificationActionSafetyService $notificationActionSafetyService,
    ) {
    }

    public function edit(Request $request): View
    {
        return $this->renderProductionPage($request, 'overview');
    }

    public function logs(Request $request): View
    {
        return $this->renderProductionPage($request, 'logs');
    }

    public function templates(Request $request): View
    {
        return $this->renderProductionPage($request, 'templates');
    }

    public function automation(Request $request): View
    {
        return $this->renderProductionPage($request, 'automation');
    }

    public function diagnostics(Request $request): View
    {
        return $this->renderProductionPage($request, 'diagnostics');
    }

    protected function renderProductionPage(Request $request, string $page): View
    {
        return view('admin.settings.notification-center.'.$page, $this->buildProductionPageData($request, $page));
    }



    protected function buildProductionPageData(Request $request, string $page): array
    {
        $meta = $this->pageMeta($page);

        return array_merge(
            $this->buildViewData($request),
            $meta,
            [
                'pageTitle' => $meta['pageTitle'] ?? __('Notification Center'),
                'pageHeading' => $meta['pageHeading'] ?? __('Notification Center'),
                'pageDescription' => $meta['pageDescription'] ?? __('Notification workspace'),
                'currentSection' => $page,
            ]
        );
    }

    protected function buildSafeOverviewData(Request $request): array
    {
        $settings = $this->settingsService->all();
        $events = OrderNotificationService::eventDefinitions();
        $channels = OrderNotificationService::supportedChannels();

        $summary = [
            'database_total' => 0,
            'database_unread' => 0,
            'whatsapp_total' => 0,
            'whatsapp_failed' => 0,
            'dispatch_total' => 0,
            'dispatch_failed' => 0,
            'dispatch_pending' => 0,
        ];

        try {
            if (Schema::hasTable('notifications')) {
                $summary['database_total'] = (int) DB::table('notifications')->count();
                $summary['database_unread'] = (int) DB::table('notifications')->whereNull('read_at')->count();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            if (Schema::hasTable('whatsapp_logs')) {
                $summary['whatsapp_total'] = (int) WhatsAppLog::query()->count();
                $summary['whatsapp_failed'] = (int) WhatsAppLog::query()->where('status', WhatsAppLog::STATUS_FAILED)->count();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            if (Schema::hasTable('notification_dispatch_logs')) {
                $summary['dispatch_total'] = (int) NotificationDispatchLog::query()->count();
                $summary['dispatch_failed'] = (int) NotificationDispatchLog::query()->where('status', NotificationDispatchLog::STATUS_FAILED)->count();
                $summary['dispatch_pending'] = (int) NotificationDispatchLog::query()->where('status', NotificationDispatchLog::STATUS_PENDING)->count();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $channelCards = collect($channels)->map(function (array $channel, string $key) use ($settings) {
            $enabled = ($settings['notification_channel_'.$key.'_enabled'] ?? '0') === '1';

            return [
                'key' => $key,
                'label' => $channel['label'] ?? ucfirst($key),
                'description' => $channel['description'] ?? '',
                'enabled' => $enabled,
            ];
        })->values();

        $eventCards = collect($events)->map(function (array $event, string $key) use ($settings) {
            $enabledChannels = [];

            foreach (array_keys(OrderNotificationService::supportedChannels()) as $channelKey) {
                if (($settings['notification_event_'.$key.'_'.$channelKey] ?? '0') === '1') {
                    $enabledChannels[] = OrderNotificationService::supportedChannels()[$channelKey]['label'] ?? ucfirst($channelKey);
                }
            }

            return [
                'key' => $key,
                'label' => $event['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                'description' => $event['description'] ?? '',
                'enabled_channels' => $enabledChannels,
            ];
        })->values();

        return [
            'pageTitle' => __('Notification Center'),
            'settings' => $settings,
            'summary' => $summary,
            'channelCards' => $channelCards,
            'eventCards' => $eventCards,
            'moduleLinks' => [
                ['label' => __('Admin Inbox'), 'route' => route('admin.notifications.index'), 'style' => 'primary'],
                ['label' => __('Logs & retry'), 'route' => route('admin.settings.notifications.logs'), 'style' => 'dark'],
                ['label' => __('Templates'), 'route' => route('admin.settings.notifications.templates'), 'style' => 'light'],
                ['label' => __('Automation'), 'route' => route('admin.settings.notifications.automation'), 'style' => 'light'],
                ['label' => __('Diagnostics'), 'route' => route('admin.settings.notifications.diagnostics'), 'style' => 'light'],
            ],
            'successMessage' => session('success'),
        ];
    }


    protected function buildSafeModuleData(Request $request, string $page): array
    {
        $base = $this->buildSafeOverviewData($request);
        $meta = $this->pageMeta($page);

        $moduleCards = [];
        $secondaryCards = [];

        try {
            if ($page === 'logs') {
                $failedDispatch = collect();
                $failedWhatsApp = collect();
                $recentActivity = collect();

                if (Schema::hasTable('notification_dispatch_logs')) {
                    $failedDispatch = NotificationDispatchLog::query()
                        ->with('order:id,order_number,customer_name')
                        ->whereIn('status', [NotificationDispatchLog::STATUS_FAILED, NotificationDispatchLog::STATUS_SKIPPED, NotificationDispatchLog::STATUS_PENDING])
                        ->latest('id')
                        ->limit(8)
                        ->get()
                        ->map(fn ($log) => [
                            'title' => $log->event ?: __('Dispatch log'),
                            'meta' => trim(($log->channel ?: __('Channel')).' · '.($log->status ?: __('Unknown'))),
                            'description' => optional($log->order)->order_number ?: __('No order linked'),
                        ]);
                }

                if (Schema::hasTable('whatsapp_logs')) {
                    $failedWhatsApp = WhatsAppLog::query()
                        ->with('order:id,order_number,customer_name')
                        ->whereIn('status', [WhatsAppLog::STATUS_FAILED, WhatsAppLog::STATUS_SKIPPED])
                        ->latest('id')
                        ->limit(8)
                        ->get()
                        ->map(fn ($log) => [
                            'title' => $log->template_name ?: __('WhatsApp log'),
                            'meta' => $log->status ?: __('Unknown'),
                            'description' => optional($log->order)->order_number ?: ($log->error_message ?: __('No order linked')),
                        ]);
                }

                if (Schema::hasTable('admin_activity_logs')) {
                    $recentActivity = AdminActivityLog::query()
                        ->with('adminUser:id,name')
                        ->latest('id')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => [
                            'title' => $row->description ?: __('Admin activity'),
                            'meta' => trim(($row->type ?: __('Type')).' · '.optional($row->adminUser)->name),
                            'description' => optional($row->created_at)?->diffForHumans() ?: __('Just now'),
                        ]);
                }

                $moduleCards = [
                    ['title' => __('Failed dispatch logs'), 'items' => $failedDispatch],
                    ['title' => __('Failed WhatsApp logs'), 'items' => $failedWhatsApp],
                    ['title' => __('Recent admin activity'), 'items' => $recentActivity],
                ];
                $secondaryCards = [
                    ['label' => __('Logs total'), 'value' => $base['summary']['dispatch_total'] ?? 0],
                    ['label' => __('Dispatch failed'), 'value' => $base['summary']['dispatch_failed'] ?? 0],
                    ['label' => __('WhatsApp failed'), 'value' => $base['summary']['whatsapp_failed'] ?? 0],
                ];
            } elseif ($page === 'templates') {
                $templateCount = 0;
                if (Schema::hasTable('notification_message_templates')) {
                    $templateCount = (int) DB::table('notification_message_templates')->count();
                }

                $moduleCards = [
                    ['title' => __('Supported channels'), 'items' => collect(OrderNotificationService::supportedChannels())->map(fn ($channel, $key) => [
                        'title' => $channel['label'] ?? ucfirst($key),
                        'meta' => $key,
                        'description' => $channel['description'] ?? '',
                    ])->values()],
                    ['title' => __('Supported events'), 'items' => collect(OrderNotificationService::eventDefinitions())->map(fn ($event, $key) => [
                        'title' => $event['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                        'meta' => $key,
                        'description' => $event['description'] ?? '',
                    ])->values()->take(8)],
                ];
                $secondaryCards = [
                    ['label' => __('Templates saved'), 'value' => $templateCount],
                    ['label' => __('Supported locales'), 'value' => count(NotificationTemplateService::supportedLocales())],
                    ['label' => __('Events available'), 'value' => count(OrderNotificationService::eventDefinitions())],
                ];
            } elseif ($page === 'automation') {
                $rules = collect();
                $scannerHistory = collect();
                $staleRecovered = collect();

                if (Schema::hasTable('notification_automation_rules')) {
                    $rules = NotificationAutomationRule::query()
                        ->latest('id')
                        ->limit(8)
                        ->get()
                        ->map(fn ($rule) => [
                            'title' => $rule->name ?: __('Automation rule'),
                            'meta' => trim(($rule->trigger_status ?: __('Trigger')).' · '.($rule->action ?: __('Action'))),
                            'description' => $rule->event ?: __('Applies to all events'),
                        ]);
                }

                if (Schema::hasTable('admin_activity_logs')) {
                    $scannerHistory = AdminActivityLog::query()
                        ->with('adminUser:id,name')
                        ->where('type', 'notification_automation')
                        ->latest('id')
                        ->limit(8)
                        ->get()
                        ->map(fn ($row) => [
                            'title' => $row->description ?: __('Scanner activity'),
                            'meta' => optional($row->adminUser)->name ?: __('System'),
                            'description' => optional($row->created_at)?->diffForHumans() ?: __('Just now'),
                        ]);
                }

                if (Schema::hasTable('notification_dispatch_logs')) {
                    $staleRecovered = NotificationDispatchLog::query()
                        ->with('order:id,order_number,customer_name')
                        ->where('meta->stale_pending_recovered', true)
                        ->latest('updated_at')
                        ->limit(8)
                        ->get()
                        ->map(fn ($log) => [
                            'title' => optional($log->order)->order_number ?: __('Recovered log'),
                            'meta' => $log->status ?: __('Unknown'),
                            'description' => optional($log->updated_at)?->diffForHumans() ?: __('Just now'),
                        ]);
                }

                $moduleCards = [
                    ['title' => __('Automation rules'), 'items' => $rules],
                    ['title' => __('Scanner history'), 'items' => $scannerHistory],
                    ['title' => __('Stale pending recovery'), 'items' => $staleRecovered],
                ];
                $secondaryCards = [
                    ['label' => __('Rules count'), 'value' => $rules->count()],
                    ['label' => __('Dispatch pending'), 'value' => $base['summary']['dispatch_pending'] ?? 0],
                    ['label' => __('Dispatch failed'), 'value' => $base['summary']['dispatch_failed'] ?? 0],
                ];
            } else {
                $monitoring = [];
                try {
                    $monitoring = $this->queueMonitoringService->dashboard();
                } catch (\Throwable $e) {
                    report($e);
                    $monitoring = [];
                }

                $healthItems = collect([
                    [
                        'title' => __('In-app / database'),
                        'meta' => ($base['summary']['database_total'] ?? 0).' '.__('records'),
                        'description' => __('Unread: :count', ['count' => $base['summary']['database_unread'] ?? 0]),
                    ],
                    [
                        'title' => __('Dispatch pipeline'),
                        'meta' => __('Pending: :count', ['count' => $base['summary']['dispatch_pending'] ?? 0]),
                        'description' => __('Failed: :count', ['count' => $base['summary']['dispatch_failed'] ?? 0]),
                    ],
                    [
                        'title' => __('WhatsApp channel'),
                        'meta' => __('Total: :count', ['count' => $base['summary']['whatsapp_total'] ?? 0]),
                        'description' => __('Failed: :count', ['count' => $base['summary']['whatsapp_failed'] ?? 0]),
                    ],
                ]);

                $moduleCards = [
                    ['title' => __('Health snapshot'), 'items' => $healthItems],
                    ['title' => __('Channel snapshot'), 'items' => collect($base['channelCards'])->map(fn ($channel) => [
                        'title' => $channel['label'],
                        'meta' => $channel['enabled'] ? __('Enabled') : __('Disabled'),
                        'description' => $channel['description'],
                    ])],
                ];
                $secondaryCards = [
                    ['label' => __('Queue health'), 'value' => data_get($monitoring, 'health.label', __('Unknown'))],
                    ['label' => __('Recommended action'), 'value' => data_get($monitoring, 'next_action', __('Review diagnostics'))],
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return array_merge($base, $meta, [
            'pageTitle' => $meta['pageTitle'] ?? __('Notification Center'),
            'pageHeading' => $meta['pageHeading'] ?? __('Notification Center'),
            'pageDescription' => $meta['pageDescription'] ?? __('Notification workspace'),
            'currentSection' => $page,
            'moduleCards' => $moduleCards,
            'secondaryCards' => $secondaryCards,
        ]);
    }

    protected function pageMeta(string $page): array
    {
        $meta = [
            'overview' => [
                'pageTitle' => __('Notification overview'),
                'pageHeading' => __('Notification overview'),
                'pageDescription' => __('Start from the live health snapshot, keep channel controls close, and leave retries, automation, and diagnostics to their dedicated pages.'),
                'pageIntro' => __('This is now the main entry point for the notification module: summary first, controls second, and deeper work separated into dedicated pages.'),
                'quickAccessText' => __('Save channel changes here, then open logs for retries, templates for message content, automation for rules, and diagnostics for queue-level investigation.'),
            ],
            'logs' => [
                'pageTitle' => __('Notification logs & retry'),
                'pageHeading' => __('Logs and retry center'),
                'pageDescription' => __('Review delivery history, isolate failures, retry safely, and follow operator activity without the rest of the settings workspace getting in the way.'),
                'pageIntro' => __('This page is dedicated to recovery work only: search the logs, retry safely, and review the admin-side timeline.'),
                'quickAccessText' => __('Use this page when something failed or needs escalation. For channel setup go back to Overview; for content changes use Templates.'),
            ],
            'templates' => [
                'pageTitle' => __('Notification templates & test send'),
                'pageHeading' => __('Templates and test send'),
                'pageDescription' => __('Manage message copy, preview localized output, and run controlled test sends from a dedicated content workspace.'),
                'pageIntro' => __('This page focuses only on content quality: edit the templates, preview their final output, and validate them with safe test sends.'),
                'quickAccessText' => __('Keep wording changes here so the team does not mix customer-facing copy work with queue recovery or automation tuning.'),
            ],
            'automation' => [
                'pageTitle' => __('Notification automation & escalation'),
                'pageHeading' => __('Automation and escalation'),
                'pageDescription' => __('Control automation rules, scanner behavior, escalation safety, and stale pending recovery from one focused policy page.'),
                'pageIntro' => __('This page is now dedicated to policy logic: edit the rules, run the scanner, and review recent automation activity without the rest of the workspace competing for attention.'),
                'quickAccessText' => __('Open this page when you are shaping automation behavior. Use Logs for active incidents and Diagnostics for queue-level investigation.'),
            ],
            'diagnostics' => [
                'pageTitle' => __('Notification diagnostics'),
                'pageHeading' => __('Diagnostics and monitoring'),
                'pageDescription' => __('Inspect queue health, worker resilience, provider tuning, release checks, and recommendations from one dedicated advanced page.'),
                'pageIntro' => __('This page keeps the advanced operational details together so the main notification workflow stays lighter and easier to scan.'),
                'quickAccessText' => __('Use this page only when health drops, a worker issue is suspected, or you need a deeper operational audit before taking action.'),
            ],
        ];

        return array_merge($meta[$page] ?? $meta['overview'], [
            'currentSection' => $page,
        ]);
    }

    protected function buildViewData(Request $request): array
    {
        $this->notificationTemplateService->ensureDefaults();
        $this->notificationAutomationService->ensureDefaults();

        $settings = $this->settingsService->all();
        $events = OrderNotificationService::eventDefinitions();
        $channels = OrderNotificationService::supportedChannels();
        $locales = NotificationTemplateService::supportedLocales();

        $summary = [
            'database_total' => 0,
            'database_unread' => 0,
            'whatsapp_total' => 0,
            'whatsapp_sent' => 0,
            'whatsapp_failed' => 0,
            'dispatch_total' => 0,
            'dispatch_sent' => 0,
            'dispatch_failed' => 0,
            'dispatch_pending' => 0,
        ];

        if (Schema::hasTable('notifications')) {
            $summary['database_total'] = (int) DB::table('notifications')->count();
            $summary['database_unread'] = (int) DB::table('notifications')->whereNull('read_at')->count();
        }

        if (Schema::hasTable('whatsapp_logs')) {
            $summary['whatsapp_total'] = (int) WhatsAppLog::query()->count();
            $summary['whatsapp_sent'] = (int) WhatsAppLog::query()->where('status', WhatsAppLog::STATUS_SENT)->count();
            $summary['whatsapp_failed'] = (int) WhatsAppLog::query()->where('status', WhatsAppLog::STATUS_FAILED)->count();
        }

        $dispatchLogs = collect();
        $failedDispatchLogs = collect();
        $failedWhatsAppLogs = collect();
        $activityTimeline = collect();
        $scannerHistory = collect();
        $staleRecoveredLogs = collect();

        if (Schema::hasTable('notification_dispatch_logs')) {
            $staleRecoveredLogs = NotificationDispatchLog::query()
                ->with('order:id,order_number,customer_name')
                ->where('meta->stale_pending_recovered', true)
                ->latest('updated_at')
                ->limit(8)
                ->get();

            $dispatchQuery = NotificationDispatchLog::query()
                ->with(['order:id,order_number,customer_name,customer_phone,customer_email', 'user:id,name,email'])
                ->search((string) $request->string('search'))
                ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->string('channel')))
                ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->latest('id');

            $dispatchLogs = $dispatchQuery->paginate(12)->withQueryString();

            $summary['dispatch_total'] = (int) NotificationDispatchLog::query()->count();
            $summary['dispatch_sent'] = (int) NotificationDispatchLog::query()->where('status', NotificationDispatchLog::STATUS_SENT)->count();
            $summary['dispatch_failed'] = (int) NotificationDispatchLog::query()->where('status', NotificationDispatchLog::STATUS_FAILED)->count();
            $summary['dispatch_pending'] = (int) NotificationDispatchLog::query()->where('status', NotificationDispatchLog::STATUS_PENDING)->count();

            $failedDispatchLogs = NotificationDispatchLog::query()
                ->with('order:id,order_number,customer_name')
                ->whereIn('status', [NotificationDispatchLog::STATUS_FAILED, NotificationDispatchLog::STATUS_SKIPPED])
                ->latest('id')
                ->limit(8)
                ->get();
        }

        if (Schema::hasTable('whatsapp_logs')) {
            $failedWhatsAppLogs = WhatsAppLog::query()
                ->with('order:id,order_number,customer_name,customer_phone')
                ->whereIn('status', [WhatsAppLog::STATUS_FAILED, WhatsAppLog::STATUS_SKIPPED])
                ->latest('id')
                ->limit(8)
                ->get();
        }

        if (Schema::hasTable('admin_activity_logs')) {
            $activityTimeline = AdminActivityLog::query()
                ->with('adminUser:id,name,email')
                ->latest('id')
                ->limit(20)
                ->get();

            $scannerHistory = AdminActivityLog::query()
                ->with('adminUser:id,name,email')
                ->where('type', 'notification_automation')
                ->whereIn('action', ['manual_scanner_run', 'scheduled_scanner_run'])
                ->latest('id')
                ->limit(8)
                ->get();
        }

        $previewOrder = Order::query()
            ->with('user:id,name,email')
            ->when($request->filled('preview_order_id'), fn ($query) => $query->whereKey((int) $request->integer('preview_order_id')))
            ->latest('id')
            ->first();

        if (! $previewOrder) {
            $previewOrder = Order::query()->with('user:id,name,email')->latest('id')->first();
        }

        $previewState = [
            'order_id' => $previewOrder?->id,
            'event' => (string) ($request->string('preview_event') ?: OrderNotificationService::EVENT_STATUS_UPDATED),
            'channel' => (string) ($request->string('preview_channel') ?: 'email'),
            'locale' => (string) ($request->string('preview_locale') ?: ($previewOrder ? $this->notificationTemplateService->resolveLocaleForOrder($previewOrder) : 'ar')),
        ];

        $preview = $previewOrder
            ? $this->notificationTemplateService->renderForOrder($previewOrder, $previewState['event'], $previewState['channel'], $previewState['locale'])
            : null;

        $monitoring = $this->queueMonitoringService->dashboard();
        $automation = $this->notificationAutomationService->dashboard();

        return [
            'settings' => $settings,
            'events' => $events,
            'channels' => $channels,
            'locales' => $locales,
            'statusColors' => [
                'sent' => 'success',
                'pending' => 'warning',
                'failed' => 'danger',
                'skipped' => 'secondary',
            ],
            'statusLabels' => [
                'sent' => __('Sent'),
                'pending' => __('Pending'),
                'failed' => __('Failed'),
                'skipped' => __('Skipped'),
            ],
            'triggerLabels' => [
                'failed' => __('Failed'),
                'skipped' => __('Skipped'),
                'pending_stale' => __('Pending stale'),
            ],
            'actionLabels' => [
                'retry_same_channel' => __('Retry same channel'),
                'fallback_channel' => __('Fallback channel'),
                'notify_admin_email' => __('Notify admin email'),
                'create_activity' => __('Create admin activity'),
            ],
            'healthLabels' => [
                'healthy' => __('Healthy'),
                'warning' => __('Warning'),
                'critical' => __('Critical'),
            ],
            'moduleLinks' => [
                'overview' => ['label' => __('Overview'), 'route' => route('admin.settings.notifications')],
                'logs' => ['label' => __('Logs & retry'), 'route' => route('admin.settings.notifications.logs')],
                'templates' => ['label' => __('Templates & test send'), 'route' => route('admin.settings.notifications.templates')],
                'automation' => ['label' => __('Automation & escalation'), 'route' => route('admin.settings.notifications.automation')],
                'diagnostics' => ['label' => __('Diagnostics'), 'route' => route('admin.settings.notifications.diagnostics')],
            ],
            'summary' => $summary,
            'dispatchLogs' => $dispatchLogs,
            'failedDispatchLogs' => $failedDispatchLogs,
            'failedWhatsAppLogs' => $failedWhatsAppLogs,
            'activityTimeline' => $activityTimeline,
            'templateMatrix' => $this->notificationTemplateService->groupedTemplates(),
            'recentOrders' => Order::query()->latest('id')->limit(20)->get(['id', 'order_number', 'customer_name', 'customer_email', 'customer_phone']),
            'previewOrder' => $previewOrder,
            'previewState' => $previewState,
            'preview' => $preview,
            'monitoring' => $monitoring,
            'automation' => $automation,
            'automationRules' => $automation['rules'] ?? collect(),
            'scannerHistory' => $scannerHistory,
            'staleRecoveredLogs' => $staleRecoveredLogs,
            'filters' => [
                'search' => (string) $request->string('search'),
                'channel' => (string) $request->string('channel'),
                'event' => (string) $request->string('event'),
                'status' => (string) $request->string('status'),
            ],
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $events = array_keys(OrderNotificationService::eventDefinitions());
        $channels = array_keys(OrderNotificationService::supportedChannels());

        $rules = [
            'notification_center_enabled' => ['nullable', 'boolean'],
        ];

        foreach ($channels as $channel) {
            $rules['notification_channel_'.$channel.'_enabled'] = ['nullable', 'boolean'];
        }

        foreach ($events as $event) {
            foreach ($channels as $channel) {
                $rules['notification_event_'.$event.'_'.$channel] = ['nullable', 'boolean'];
            }
        }

        $request->validate($rules);

        $payload = [
            'notification_center_enabled' => $request->boolean('notification_center_enabled') ? '1' : '0',
        ];

        foreach ($channels as $channel) {
            $payload['notification_channel_'.$channel.'_enabled'] = $request->boolean('notification_channel_'.$channel.'_enabled') ? '1' : '0';
        }

        foreach ($events as $event) {
            foreach ($channels as $channel) {
                $payload['notification_event_'.$event.'_'.$channel] = $request->boolean('notification_event_'.$event.'_'.$channel) ? '1' : '0';
            }
        }

        if (($payload['notification_channel_whatsapp_enabled'] ?? '0') !== '1') {
            foreach ($events as $event) {
                $payload['notification_event_'.$event.'_whatsapp'] = '0';
            }
        }

        if (($payload['notification_channel_sms_enabled'] ?? '0') !== '1') {
            foreach ($events as $event) {
                $payload['notification_event_'.$event.'_sms'] = '0';
            }
        }

        $this->settingsService->save($payload);
        $this->adminActivityLogService->log(
            'notification_center',
            'settings_updated',
            __('Notification center settings were updated.'),
            optional(auth()->user())->id,
            null,
            [
                'engine_enabled' => $payload['notification_center_enabled'] ?? '0',
                'channels' => collect($channels)->mapWithKeys(fn ($channel) => [$channel => $payload['notification_channel_'.$channel.'_enabled'] ?? '0'])->all(),
            ]
        );

        return back()->with('success', __('Notification center settings were saved successfully.'));
    }

    public function saveTemplate(Request $request): RedirectResponse
    {
        $events = array_keys(OrderNotificationService::eventDefinitions());
        $channels = array_keys(OrderNotificationService::supportedChannels());
        $locales = array_keys(NotificationTemplateService::supportedLocales());

        $validated = $request->validate([
            'event' => ['required', Rule::in($events)],
            'channel' => ['required', Rule::in($channels)],
            'locale' => ['required', Rule::in($locales)],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'tokens_text' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = $this->notificationTemplateService->saveTemplate(
            $validated['event'],
            $validated['channel'],
            $validated['locale'],
            $request->all()
        );

        $this->adminActivityLogService->log(
            'notification_template',
            'template_saved',
            __('Notification template :name was updated.', ['name' => $template->name]),
            optional(auth()->user())->id,
            null,
            [
                'notification_template_id' => $template->id,
                'event' => $template->event,
                'channel' => $template->channel,
                'locale' => $template->locale,
            ]
        );

        return back()->with('success', __('Notification template was saved successfully.'));
    }

    public function saveAutomationRule(Request $request): RedirectResponse
    {
        $events = array_keys(OrderNotificationService::eventDefinitions());
        $channels = array_keys(OrderNotificationService::supportedChannels());
        $triggerStatuses = [
            NotificationDispatchLog::STATUS_FAILED,
            NotificationDispatchLog::STATUS_SKIPPED,
            NotificationAutomationRule::TRIGGER_PENDING_STALE,
        ];
        $actions = [
            NotificationAutomationRule::ACTION_RETRY_SAME_CHANNEL,
            NotificationAutomationRule::ACTION_FALLBACK_CHANNEL,
            NotificationAutomationRule::ACTION_NOTIFY_ADMIN_EMAIL,
            NotificationAutomationRule::ACTION_CREATE_ACTIVITY,
        ];

        $validated = $request->validate([
            'rule_id' => ['nullable', 'integer', 'exists:notification_automation_rules,id'],
            'name' => ['required', 'string', 'max:255'],
            'event' => ['nullable', Rule::in($events)],
            'trigger_status' => ['required', Rule::in($triggerStatuses)],
            'source_channel' => ['nullable', Rule::in($channels)],
            'action_type' => ['required', Rule::in($actions)],
            'target_channel' => ['nullable', Rule::in($channels)],
            'escalation_level' => ['required', 'integer', 'min:1', 'max:5'],
            'delay_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'admin_email' => ['nullable', 'email:rfc'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'rate_limit_window_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rate_limit_max_runs' => ['nullable', 'integer', 'min:1', 'max:20'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = $this->notificationAutomationService->saveRule($request->all());

        $this->adminActivityLogService->log(
            'notification_automation',
            'rule_saved',
            __('Notification automation rule :name was saved.', ['name' => $rule->name]),
            optional(auth()->user())->id,
            null,
            [
                'notification_automation_rule_id' => $rule->id,
                'trigger_status' => $rule->trigger_status,
                'action_type' => $rule->action_type,
                'source_channel' => $rule->source_channel,
                'target_channel' => $rule->target_channel,
            ]
        );

        return back()->with('success', __('Notification automation rule was saved successfully.'));
    }


    public function runScanner(): RedirectResponse
    {
        $result = $this->notificationAutomationService->scanStalePending(true);

        $this->adminActivityLogService->log(
            'notification_automation',
            'manual_scanner_run',
            __('Manual stale pending scanner run completed.'),
            optional(auth()->user())->id,
            null,
            $result
        );

        return back()->with('success', __('Scanner run completed. Scanned: :scanned | Matched: :matched | Recovered: :recovered', $result));
    }

    public function runEscalation(NotificationDispatchLog $log): RedirectResponse
    {
        $executed = $this->notificationAutomationService->executeForDispatch($log, true);

        $this->adminActivityLogService->log(
            'notification_automation',
            'manual_escalation_run',
            __('Manual escalation policies were executed for notification log #:id.', ['id' => $log->id]),
            optional(auth()->user())->id,
            $log->order,
            [
                'notification_dispatch_log_id' => $log->id,
                'executed_rules' => $executed,
            ]
        );

        if (empty($executed)) {
            return back()->with('error', __('No active automation rules matched this notification log yet.'));
        }

        return back()->with('success', __('Escalation policies were executed successfully.'));
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $events = array_keys(OrderNotificationService::eventDefinitions());
        $channels = array_keys(OrderNotificationService::supportedChannels());
        $locales = array_keys(NotificationTemplateService::supportedLocales());

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'event' => ['required', Rule::in($events)],
            'channel' => ['required', Rule::in($channels)],
            'locale' => ['required', Rule::in($locales)],
            'test_email' => ['nullable', 'email:rfc'],
        ]);

        $order = Order::query()->with('user:id,name,email')->findOrFail((int) $validated['order_id']);

        try {
            $this->notificationTemplateService->sendTest(
                $order,
                $validated['event'],
                $validated['channel'],
                $validated['locale'],
                $validated['test_email'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->adminActivityLogService->log(
            'notification_test_send',
            'test_sent',
            __('A notification test send was triggered for :channel / :event.', [
                'channel' => $validated['channel'],
                'event' => $validated['event'],
            ]),
            optional(auth()->user())->id,
            $order,
            [
                'channel' => $validated['channel'],
                'event' => $validated['event'],
                'locale' => $validated['locale'],
                'test_email' => $validated['test_email'] ?? null,
            ]
        );

        return back()->with('success', __('Notification test send was executed successfully.'));
    }

    public function retryFailedQueueJob(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'failed_job_id' => ['required', 'string', 'max:255'],
        ]);

        $inspection = $this->notificationActionSafetyService->inspectFailedQueueRetry($validated['failed_job_id']);

        if (! ($inspection['allowed'] ?? false)) {
            $this->adminActivityLogService->log(
                'queue_recovery',
                'failed_job_retry_blocked',
                __('A failed queue job retry was blocked by a production safety guard.'),
                optional(auth()->user())->id,
                null,
                $inspection
            );

            return back()->with('error', $inspection['message'] ?? __('Queue retry was blocked by a safety guard.'));
        }

        try {
            $result = $this->queueRecoveryService->retryFailedJob($validated['failed_job_id']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->adminActivityLogService->log(
            'queue_recovery',
            'failed_job_retry_requested',
            __('A failed queue job retry was requested from the admin monitoring center.'),
            optional(auth()->user())->id,
            null,
            array_merge($inspection, $result)
        );

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? __('Failed queue job retry was requested successfully.')
                : __('Queue retry command finished with warnings: :output', ['output' => $result['output'] ?: __('No command output')])
        );
    }

    public function retryLog(NotificationDispatchLog $log): RedirectResponse
    {
        $inspection = $this->notificationActionSafetyService->inspectDispatchRetry($log);

        if (! ($inspection['allowed'] ?? false)) {
            $this->adminActivityLogService->log(
                'notification_retry',
                'dispatch_log_retry_blocked',
                __('A notification dispatch retry was blocked by a production safety guard.'),
                optional(auth()->user())->id,
                $log->order,
                array_merge($inspection, [
                    'notification_dispatch_log_id' => $log->id,
                    'channel' => $log->channel,
                    'event' => $log->event,
                ])
            );

            return back()->with('error', $inspection['message'] ?? __('This notification retry was blocked by a safety guard.'));
        }

        $result = $this->orderNotificationService->retryDispatchLog($log);

        $this->adminActivityLogService->log(
            'notification_retry',
            ($result['ok'] ?? false) ? 'dispatch_log_retried' : 'dispatch_log_retry_failed',
            ($result['ok'] ?? false)
                ? __('A notification dispatch retry was requested for :channel on event :event.', [
                    'channel' => $log->channel,
                    'event' => $log->event,
                ])
                : __('A notification dispatch retry could not be completed safely.'),
            optional(auth()->user())->id,
            $log->order,
            array_merge($inspection, $result, [
                'notification_dispatch_log_id' => $log->id,
                'channel' => $log->channel,
                'event' => $log->event,
            ])
        );

        return back()->with(($result['ok'] ?? false) ? 'success' : 'error', $result['message'] ?? __('Notification retry finished.'));
    }

    public function retryWhatsAppLog(WhatsAppLog $log): RedirectResponse
    {
        $inspection = $this->notificationActionSafetyService->inspectWhatsAppRetry($log);

        if (! ($inspection['allowed'] ?? false)) {
            $this->adminActivityLogService->log(
                'notification_retry',
                'whatsapp_log_retry_blocked',
                __('A WhatsApp retry was blocked by a production safety guard.'),
                optional(auth()->user())->id,
                $log->order,
                array_merge($inspection, [
                    'whatsapp_log_id' => $log->id,
                    'message_type' => $log->message_type,
                    'status' => $log->status,
                ])
            );

            return back()->with('error', $inspection['message'] ?? __('This WhatsApp retry was blocked by a safety guard.'));
        }

        $retried = $this->whatsAppService->retry($log);

        $this->adminActivityLogService->log(
            'notification_retry',
            $retried ? 'whatsapp_log_retried' : 'whatsapp_log_retry_failed',
            $retried
                ? __('A WhatsApp retry was requested for :type.', ['type' => $log->message_type])
                : __('A WhatsApp retry could not be completed safely.'),
            optional(auth()->user())->id,
            $log->order,
            array_merge($inspection, [
                'whatsapp_log_id' => $log->id,
                'message_type' => $log->message_type,
                'status' => $log->status,
                'retried_log_id' => $retried?->id,
            ])
        );

        return back()->with($retried ? 'success' : 'error', $retried
            ? __('WhatsApp retry was queued safely.')
            : __('WhatsApp retry could not be completed safely.'));
    }
}
