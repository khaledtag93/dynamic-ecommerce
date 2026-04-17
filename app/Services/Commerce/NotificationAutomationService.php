<?php

namespace App\Services\Commerce;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Mail\OrderUpdateMail;
use App\Models\NotificationAutomationRule;
use App\Models\NotificationDispatchLog;
use App\Models\Order;
use App\Notifications\DeliveryStatusUpdatedNotification;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificationAutomationService
{
    public function __construct(
        protected StoreSettingsService $storeSettingsService,
        protected NotificationTemplateService $notificationTemplateService,
        protected AdminActivityLogService $adminActivityLogService,
        protected WhatsAppServiceInterface $whatsAppService,
    ) {
    }

    public function ensureDefaults(): void
    {
        if (! Schema::hasTable('notification_automation_rules')) {
            return;
        }

        foreach ($this->defaultRules() as $rule) {
            NotificationAutomationRule::query()->updateOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }
    }

    public function dashboard(): array
    {
        if (! Schema::hasTable('notification_automation_rules')) {
            return [
                'total_rules' => 0,
                'active_rules' => 0,
                'escalation_levels' => [],
                'rules' => collect(),
                'recent_escalations' => collect(),
                'triggered_last_24h' => 0,
                'scanner' => $this->scannerDashboard(),
            ];
        }

        $rules = NotificationAutomationRule::query()->orderBy('sort_order')->orderBy('id')->get();
        $recentEscalations = Schema::hasTable('notification_dispatch_logs')
            ? NotificationDispatchLog::query()
                ->with('order:id,order_number,customer_name')
                ->whereNotNull('meta->automation_run')
                ->latest('id')
                ->limit(10)
                ->get()
            : collect();

        $triggeredLast24h = Schema::hasTable('notification_dispatch_logs')
            ? (int) NotificationDispatchLog::query()
                ->whereNotNull('meta->automation_run')
                ->where('created_at', '>=', now()->subDay())
                ->count()
            : 0;

        return [
            'total_rules' => $rules->count(),
            'active_rules' => $rules->where('is_active', true)->count(),
            'escalation_levels' => $rules->groupBy('escalation_level')->map->count()->sortKeys()->all(),
            'rules' => $rules,
            'recent_escalations' => $recentEscalations,
            'triggered_last_24h' => $triggeredLast24h,
            'scanner' => $this->scannerDashboard(),
        ];
    }

    public function saveRule(array $data): NotificationAutomationRule
    {
        $rule = isset($data['rule_id']) && $data['rule_id']
            ? NotificationAutomationRule::query()->findOrFail((int) $data['rule_id'])
            : new NotificationAutomationRule();

        $meta = array_merge((array) ($rule->meta ?? []), [
            'updated_from_admin_center' => true,
            'cooldown_minutes' => max(0, (int) ($data['cooldown_minutes'] ?? 15)),
            'rate_limit_window_minutes' => max(1, (int) ($data['rate_limit_window_minutes'] ?? 60)),
            'rate_limit_max_runs' => max(1, (int) ($data['rate_limit_max_runs'] ?? 3)),
            'daily_limit' => max(1, (int) ($data['daily_limit'] ?? 10)),
        ]);

        $rule->fill([
            'name' => $data['name'],
            'event' => $data['event'] ?: null,
            'trigger_status' => $data['trigger_status'],
            'source_channel' => $data['source_channel'] ?: null,
            'action_type' => $data['action_type'],
            'target_channel' => $data['target_channel'] ?: null,
            'escalation_level' => (int) ($data['escalation_level'] ?? 1),
            'delay_minutes' => (int) ($data['delay_minutes'] ?? 0),
            'max_attempts' => (int) ($data['max_attempts'] ?? 1),
            'admin_email' => $data['admin_email'] ?: null,
            'notes' => $data['notes'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'meta' => $meta,
        ]);
        $rule->save();

        return $rule;
    }

    public function executeForDispatch(?NotificationDispatchLog $log, bool $manual = false, ?string $triggerStatus = null): array
    {
        if (! $log || ! Schema::hasTable('notification_automation_rules')) {
            return [];
        }

        $order = $log->order;
        if (! $order) {
            return [];
        }

        $currentMeta = (array) ($log->meta ?? []);
        $resolvedTriggerStatus = $triggerStatus ?: $log->status;
        $processedTriggers = array_values(array_filter((array) ($currentMeta['automation_processed_triggers'] ?? [])));

        if (($currentMeta['automation_processed'] ?? false) && empty($processedTriggers)) {
            $processedTriggers = [$log->status];
        }

        if (! $manual && in_array($resolvedTriggerStatus, $processedTriggers, true)) {
            return [];
        }

        $attemptCount = ((int) ($currentMeta['retry_count'] ?? 0)) + 1;

        $rules = NotificationAutomationRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($log) {
                $query->whereNull('event')->orWhere('event', $log->event);
            })
            ->where('trigger_status', $resolvedTriggerStatus)
            ->where(function ($query) use ($log) {
                $query->whereNull('source_channel')->orWhere('source_channel', $log->channel);
            })
            ->where('max_attempts', '>=', $attemptCount)
            ->orderBy('escalation_level')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (NotificationAutomationRule $rule) use ($log, $manual) {
                if ($manual) {
                    return true;
                }

                $delayMinutes = max(0, (int) $rule->delay_minutes);
                if ($delayMinutes === 0) {
                    return true;
                }

                $referenceTime = $log->attempted_at ?: $log->created_at;

                return $referenceTime ? $referenceTime->lte(now()->subMinutes($delayMinutes)) : true;
            })
            ->values();

        $executed = [];

        foreach ($rules as $rule) {
            if (! $manual && ! $this->passesSafetyLimits($rule, $log)) {
                Log::info('Notification automation rule skipped by safety limits.', [
                    'notification_dispatch_log_id' => $log->id,
                    'notification_automation_rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                ]);

                $executed[] = [
                    'rule_id' => $rule->id,
                    'name' => $rule->name,
                    'action_type' => $rule->action_type,
                    'result' => 'skipped_by_safety_limit',
                ];

                continue;
            }
            $result = $this->applyRule($rule, $log, $order, $manual);

            $executed[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'action_type' => $rule->action_type,
                'result' => $result,
            ];
        }

        $executedActions = collect($executed)->filter(fn (array $item) => ($item['result'] ?? null) !== 'skipped_by_safety_limit')->values()->all();

        if (empty($executedActions)) {
            return [];
        }

        $processedTriggers[] = $resolvedTriggerStatus;
        $meta = array_merge($currentMeta, [
            'automation_processed' => true,
            'automation_processed_at' => now()->toDateTimeString(),
            'automation_processed_triggers' => array_values(array_unique($processedTriggers)),
            'automation_run' => [
                'manual' => $manual,
                'trigger_status' => $resolvedTriggerStatus,
                'rules' => $executedActions,
                'skipped_rules' => collect($executed)->filter(fn (array $item) => ($item['result'] ?? null) === 'skipped_by_safety_limit')->values()->all(),
            ],
        ]);

        $log->forceFill(['meta' => $meta])->save();

        return $executed;
    }



    public function scanStalePending(bool $manual = false, int $limit = 100): array
    {
        if (! Schema::hasTable('notification_automation_rules') || ! Schema::hasTable('notification_dispatch_logs')) {
            return [
                'scanned' => 0,
                'matched' => 0,
                'recovered' => 0,
                'logs' => [],
            ];
        }

        $minDelay = (int) NotificationAutomationRule::query()
            ->where('is_active', true)
            ->where('trigger_status', NotificationAutomationRule::TRIGGER_PENDING_STALE)
            ->min('delay_minutes');

        if ($minDelay < 0) {
            $minDelay = 0;
        }

        $query = NotificationDispatchLog::query()
            ->with('order:id,order_number,customer_name,customer_email,customer_phone,user_id')
            ->where('status', NotificationDispatchLog::STATUS_PENDING)
            ->where(function ($q) use ($minDelay) {
                $q->where('attempted_at', '<=', now()->subMinutes($minDelay))
                    ->orWhere(function ($nested) use ($minDelay) {
                        $nested->whereNull('attempted_at')
                            ->where('created_at', '<=', now()->subMinutes($minDelay));
                    });
            })
            ->orderBy('attempted_at')
            ->orderBy('id')
            ->limit($limit);

        $logs = $query->get();
        $recovered = 0;
        $matched = 0;
        $items = [];

        foreach ($logs as $log) {
            $executed = $this->executeForDispatch($log, $manual, NotificationAutomationRule::TRIGGER_PENDING_STALE);

            $executedActions = collect($executed)->filter(fn (array $item) => ($item['result'] ?? null) !== 'skipped_by_safety_limit')->values()->all();

        if (empty($executedActions)) {
                $items[] = [
                    'log_id' => $log->id,
                    'order_number' => $log->order?->order_number,
                    'status' => 'no_rule_matched',
                ];

                continue;
            }

            $matched++;
            $recovered++;

            $meta = array_merge((array) ($log->meta ?? []), [
                'stale_pending_recovered' => true,
                'stale_pending_recovered_at' => now()->toDateTimeString(),
                'stale_pending_manual_scan' => $manual,
                'stale_pending_rules' => $executed,
            ]);

            $log->forceFill([
                'status' => NotificationDispatchLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $log->error_message ?: __('Recovered by stale pending scanner after timeout.'),
                'meta' => $meta,
            ])->save();

            $items[] = [
                'log_id' => $log->id,
                'order_number' => $log->order?->order_number,
                'status' => 'recovered',
                'rules' => collect($executed)->pluck('name')->filter()->values()->all(),
            ];
        }

        return [
            'scanned' => $logs->count(),
            'matched' => $matched,
            'recovered' => $recovered,
            'logs' => $items,
        ];
    }

    public function scannerDashboard(): array
    {
        if (! Schema::hasTable('notification_dispatch_logs')) {
            return [
                'stale_pending_total' => 0,
                'recovered_last_24h' => 0,
                'last_scan_at' => null,
            ];
        }

        $activePendingStaleRules = NotificationAutomationRule::query()
            ->where('is_active', true)
            ->where('trigger_status', NotificationAutomationRule::TRIGGER_PENDING_STALE)
            ->get();

        $minDelay = $activePendingStaleRules->min('delay_minutes');
        $minDelay = $minDelay === null ? 0 : max(0, (int) $minDelay);

        $stalePendingTotal = $activePendingStaleRules->isEmpty()
            ? 0
            : (int) NotificationDispatchLog::query()
                ->where('status', NotificationDispatchLog::STATUS_PENDING)
                ->where(function ($query) use ($minDelay) {
                    $query->where('attempted_at', '<=', now()->subMinutes($minDelay))
                        ->orWhere(function ($nested) use ($minDelay) {
                            $nested->whereNull('attempted_at')
                                ->where('created_at', '<=', now()->subMinutes($minDelay));
                        });
                })
                ->count();

        $recoveredLast24h = (int) NotificationDispatchLog::query()
            ->where('meta->stale_pending_recovered', true)
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $lastRecovered = NotificationDispatchLog::query()
            ->where('meta->stale_pending_recovered', true)
            ->latest('updated_at')
            ->first();

        return [
            'stale_pending_total' => $stalePendingTotal,
            'recovered_last_24h' => $recoveredLast24h,
            'last_scan_at' => $lastRecovered?->updated_at?->toDateTimeString(),
        ];
    }

    protected function passesSafetyLimits(NotificationAutomationRule $rule, NotificationDispatchLog $log): bool
    {
        if (! Schema::hasTable('notification_dispatch_logs')) {
            return true;
        }

        $meta = (array) ($rule->meta ?? []);
        $cooldownMinutes = max(0, (int) ($meta['cooldown_minutes'] ?? 15));
        $windowMinutes = max(1, (int) ($meta['rate_limit_window_minutes'] ?? 60));
        $maxRuns = max(1, (int) ($meta['rate_limit_max_runs'] ?? 3));
        $dailyLimit = max(1, (int) ($meta['daily_limit'] ?? 10));

        $base = NotificationDispatchLog::query()
            ->where('order_id', $log->order_id)
            ->where('event', $log->event)
            ->where('meta->automation_rule_id', $rule->id);

        if ($cooldownMinutes > 0 && (clone $base)->where('created_at', '>=', now()->subMinutes($cooldownMinutes))->exists()) {
            return false;
        }

        if ((clone $base)->where('created_at', '>=', now()->subMinutes($windowMinutes))->count() >= $maxRuns) {
            return false;
        }

        if ((clone $base)->where('created_at', '>=', now()->subDay())->count() >= $dailyLimit) {
            return false;
        }

        return true;
    }

    protected function applyRule(NotificationAutomationRule $rule, NotificationDispatchLog $log, Order $order, bool $manual = false): string
    {
        return match ($rule->action_type) {
            NotificationAutomationRule::ACTION_RETRY_SAME_CHANNEL => $this->sendUsingChannel($order, $log->event, $log->channel, $log->message, $log->title, $rule, $manual),
            NotificationAutomationRule::ACTION_FALLBACK_CHANNEL => $this->sendUsingChannel($order, $log->event, (string) $rule->target_channel, $log->message, $log->title, $rule, $manual),
            NotificationAutomationRule::ACTION_NOTIFY_ADMIN_EMAIL => $this->notifyAdminEmail($rule, $log, $order),
            NotificationAutomationRule::ACTION_CREATE_ACTIVITY => $this->createActivity($rule, $log, $order),
            default => 'unsupported_action',
        };
    }

    protected function sendUsingChannel(Order $order, string $event, string $channel, string $message, string $title, NotificationAutomationRule $rule, bool $manual = false): string
    {
        $rendered = $this->notificationTemplateService->renderForOrder($order, $event, $channel, null, [
            'custom_message' => $message,
        ]);

        $channelTitle = (string) ($rendered['subject'] ?: $title ?: __('Order update'));
        $channelBody = (string) ($rendered['body'] ?: $message);

        $log = NotificationDispatchLog::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'event' => $event,
            'channel' => $channel,
            'status' => NotificationDispatchLog::STATUS_PENDING,
            'title' => $title ?: __('Automation escalation'),
            'message' => $channelBody,
            'recipient' => $channel === 'email' ? ($order->customer_email ?: $order->user?->email) : ($order->customer_phone ?: $order->user?->phone),
            'provider' => $channel,
            'attempted_at' => now(),
            'meta' => [
                'automation_rule_id' => $rule->id,
                'automation_rule_name' => $rule->name,
                'automation_manual' => $manual,
                'automation_run' => true,
                'escalation_level' => $rule->escalation_level,
            ],
        ]);

        try {
            if ($channel === 'database') {
                if (! $order->user) {
                    throw new \RuntimeException('Order user is missing for database notification.');
                }

                match ($event) {
                    OrderNotificationService::EVENT_DELIVERY_UPDATED => $order->user->notify(new DeliveryStatusUpdatedNotification($order)),
                    default => $order->user->notify(new OrderStatusChangedNotification($order, $channelBody)),
                };
            } elseif ($channel === 'email') {
                $email = trim((string) ($order->customer_email ?: $order->user?->email ?: ''));
                if ($email === '') {
                    throw new \RuntimeException('Order customer email is missing.');
                }

                Mail::to($email)->send(new OrderUpdateMail($order, $event, $channelTitle, $channelBody));
            } elseif ($channel === 'whatsapp') {
                if (blank($order->customer_phone) && blank($order->user?->phone ?? null)) {
                    throw new \RuntimeException('Order customer phone is missing.');
                }

                match ($event) {
                    OrderNotificationService::EVENT_STATUS_UPDATED,
                    OrderNotificationService::EVENT_CANCELLED => $this->whatsAppService->queueOrderStatusUpdate($order),
                    OrderNotificationService::EVENT_DELIVERY_UPDATED => $this->whatsAppService->queueDeliveryUpdate($order),
                    default => throw new \RuntimeException('WhatsApp fallback is not configured for this event.'),
                };
            } else {
                throw new \RuntimeException('Unsupported fallback channel.');
            }

            $log->forceFill([
                'status' => NotificationDispatchLog::STATUS_SENT,
                'sent_at' => now(),
            ])->save();

            return 'sent';
        } catch (\Throwable $e) {
            $log->forceFill([
                'status' => NotificationDispatchLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ])->save();

            return 'failed: '.$e->getMessage();
        }
    }

    protected function notifyAdminEmail(NotificationAutomationRule $rule, NotificationDispatchLog $log, Order $order): string
    {
        $email = trim((string) ($rule->admin_email ?: config('mail.from.address') ?: ''));
        if ($email === '') {
            return 'missing_admin_email';
        }

        $subject = __('Escalation: notification failure on order :order', ['order' => $order->order_number]);
        $body = __('Rule ":rule" was triggered for event :event on channel :channel. Current status: :status. Error: :error', [
            'rule' => $rule->name,
            'event' => $log->event,
            'channel' => $log->channel,
            'status' => $log->status,
            'error' => $log->error_message ?: '—',
        ]);

        Mail::raw($body, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });

        return 'admin_emailed';
    }

    protected function createActivity(NotificationAutomationRule $rule, NotificationDispatchLog $log, Order $order): string
    {
        $this->adminActivityLogService->log(
            'notification_escalation',
            'rule_triggered',
            __('Automation rule :rule escalated notification issue for order :order.', [
                'rule' => $rule->name,
                'order' => $order->order_number,
            ]),
            optional(auth()->user())->id,
            $order,
            [
                'notification_dispatch_log_id' => $log->id,
                'automation_rule_id' => $rule->id,
                'event' => $log->event,
                'channel' => $log->channel,
                'status' => $log->status,
            ]
        );

        return 'activity_created';
    }

    protected function defaultRules(): array
    {
        return [
            [
                'name' => 'Email fallback after WhatsApp failure',
                'event' => OrderNotificationService::EVENT_STATUS_UPDATED,
                'trigger_status' => NotificationDispatchLog::STATUS_FAILED,
                'source_channel' => 'whatsapp',
                'action_type' => NotificationAutomationRule::ACTION_FALLBACK_CHANNEL,
                'target_channel' => 'email',
                'escalation_level' => 1,
                'delay_minutes' => 0,
                'max_attempts' => 2,
                'admin_email' => null,
                'notes' => 'Fallback to email when WhatsApp fails on order updates.',
                'is_active' => true,
                'sort_order' => 10,
                'meta' => [
                    'cooldown_minutes' => 15,
                    'rate_limit_window_minutes' => 60,
                    'rate_limit_max_runs' => 2,
                    'daily_limit' => 6,
                ],
            ],
            [
                'name' => 'Retry email once after failure',
                'event' => null,
                'trigger_status' => NotificationDispatchLog::STATUS_FAILED,
                'source_channel' => 'email',
                'action_type' => NotificationAutomationRule::ACTION_RETRY_SAME_CHANNEL,
                'target_channel' => null,
                'escalation_level' => 1,
                'delay_minutes' => 0,
                'max_attempts' => 1,
                'admin_email' => null,
                'notes' => 'Run one controlled retry for failed emails.',
                'is_active' => true,
                'sort_order' => 20,
                'meta' => [
                    'cooldown_minutes' => 15,
                    'rate_limit_window_minutes' => 60,
                    'rate_limit_max_runs' => 2,
                    'daily_limit' => 6,
                ],
            ],
            [
                'name' => 'Notify admin on skipped notification',
                'event' => null,
                'trigger_status' => NotificationDispatchLog::STATUS_SKIPPED,
                'source_channel' => null,
                'action_type' => NotificationAutomationRule::ACTION_NOTIFY_ADMIN_EMAIL,
                'target_channel' => null,
                'escalation_level' => 2,
                'delay_minutes' => 0,
                'max_attempts' => 3,
                'admin_email' => config('mail.from.address'),
                'notes' => 'Inform admin when recipient data is missing or channel is skipped.',
                'is_active' => true,
                'sort_order' => 30,
                'meta' => [
                    'cooldown_minutes' => 15,
                    'rate_limit_window_minutes' => 60,
                    'rate_limit_max_runs' => 2,
                    'daily_limit' => 6,
                ],
            ],
            [
                'name' => 'Recover stale pending email notification',
                'event' => null,
                'trigger_status' => NotificationAutomationRule::TRIGGER_PENDING_STALE,
                'source_channel' => 'email',
                'action_type' => NotificationAutomationRule::ACTION_RETRY_SAME_CHANNEL,
                'target_channel' => null,
                'escalation_level' => 2,
                'delay_minutes' => 15,
                'max_attempts' => 1,
                'admin_email' => null,
                'notes' => 'Retry email logs that remained pending past the safe timeout.',
                'is_active' => true,
                'sort_order' => 35,
                'meta' => [
                    'cooldown_minutes' => 15,
                    'rate_limit_window_minutes' => 60,
                    'rate_limit_max_runs' => 2,
                    'daily_limit' => 6,
                ],
            ],
            [
                'name' => 'Create activity for stale pending notifications',
                'event' => null,
                'trigger_status' => NotificationAutomationRule::TRIGGER_PENDING_STALE,
                'source_channel' => null,
                'action_type' => NotificationAutomationRule::ACTION_CREATE_ACTIVITY,
                'target_channel' => null,
                'escalation_level' => 3,
                'delay_minutes' => 20,
                'max_attempts' => 1,
                'admin_email' => null,
                'notes' => 'Create an activity record when a notification stayed pending for too long.',
                'is_active' => true,
                'sort_order' => 38,
                'meta' => [
                    'cooldown_minutes' => 15,
                    'rate_limit_window_minutes' => 60,
                    'rate_limit_max_runs' => 2,
                    'daily_limit' => 6,
                ],
            ],
            [
                'name' => 'Create admin escalation activity for hard failures',
                'event' => null,
                'trigger_status' => NotificationDispatchLog::STATUS_FAILED,
                'source_channel' => null,
                'action_type' => NotificationAutomationRule::ACTION_CREATE_ACTIVITY,
                'target_channel' => null,
                'escalation_level' => 3,
                'delay_minutes' => 0,
                'max_attempts' => 3,
                'admin_email' => null,
                'notes' => 'Write an admin timeline entry for unresolved failures.',
                'is_active' => true,
                'sort_order' => 40,
                'meta' => [
                    'cooldown_minutes' => 15,
                    'rate_limit_window_minutes' => 60,
                    'rate_limit_max_runs' => 2,
                    'daily_limit' => 6,
                ],
            ],
        ];
    }
}
