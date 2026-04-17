<?php

namespace App\Services\Growth;

use App\Jobs\SendGrowthDeliveryJob;
use App\Models\GrowthCampaign;
use App\Models\GrowthDelivery;
use App\Models\GrowthExperiment;
use App\Models\GrowthMessageLog;
use App\Models\GrowthTriggerLog;
use App\Models\User;
use Illuminate\Support\Arr;

class GrowthDeliveryService
{
    public function __construct(
        protected GrowthCampaignService $growthCampaignService,
        protected GrowthDeliveryRouter $router
    ) {
    }

    public function queue(GrowthCampaign $campaign, GrowthTriggerLog $triggerLog, array $render, ?GrowthExperiment $experiment = null, ?array $variant = null): GrowthDelivery
    {
        $user = $triggerLog->user_id ? User::query()->find($triggerLog->user_id) : null;
        $scheduledFor = ! empty($render['scheduled_for']) ? now()->parse($render['scheduled_for']) : now();
        $isSimulated = $campaign->channel === 'email' && ! $this->growthCampaignService->realEmailEnabled();
        $dueNow = $scheduledFor->lte(now());
        $status = $isSimulated && $dueNow ? 'simulated' : ($dueNow ? 'pending' : 'scheduled');

        $delivery = GrowthDelivery::query()->create([
            'campaign_id' => $campaign->id,
            'trigger_log_id' => $triggerLog->id,
            'experiment_id' => $experiment?->id,
            'user_id' => $user?->id,
            'channel' => $campaign->channel,
            'provider' => $campaign->channel === 'email' ? $this->growthCampaignService->deliveryProvider() : 'database',
            'status' => $status,
            'recipient' => $campaign->channel === 'email' ? ($user?->email) : ($user ? 'user:'.$user->id : null),
            'subject' => (string) ($render['subject'] ?? ''),
            'message' => (string) ($render['message'] ?? ''),
            'experiment_variant' => Arr::get($variant, 'key'),
            'payload' => $render,
            'meta' => [
                'locale' => $render['locale'] ?? app()->getLocale(),
                'campaign_key' => $campaign->campaign_key,
                'template_key' => $render['template_key'] ?? $campaign->default_template_key,
                'coupon_code' => $render['coupon_code'] ?? $campaign->coupon_code,
                'experiment_key' => $experiment?->experiment_key,
                'variant_name' => Arr::get($variant, 'name'),
                'simulated_email' => $isSimulated,
                'offer_label' => $render['offer_label'] ?? null,
                'timing_reason' => $render['timing_reason'] ?? null,
                'timing_score' => $render['timing_score'] ?? null,
                'decision_reason' => $render['decision_reason'] ?? null,
            ],
            'attempts' => 0,
            'scheduled_for' => $scheduledFor,
        ]);

        if ($dueNow) {
            SendGrowthDeliveryJob::dispatchSync($delivery->id);
        }

        return $delivery;
    }

    public function retry(GrowthDelivery $delivery): void
    {
        $simulated = $delivery->channel === 'email' && ! $this->growthCampaignService->realEmailEnabled();
        $delivery->update([
            'status' => $simulated ? 'simulated' : 'pending',
            'error' => null,
            'failed_at' => null,
            'scheduled_for' => now(),
        ]);

        SendGrowthDeliveryJob::dispatchSync($delivery->id);
    }

    public function processDuePending(int $limit = 100): int
    {
        $deliveries = GrowthDelivery::query()
            ->whereIn('status', ['pending', 'scheduled', 'simulated'])
            ->where(function ($query) {
                $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            SendGrowthDeliveryJob::dispatchSync($delivery->id);
        }

        return $deliveries->count();
    }

    public function send(int $deliveryId): GrowthDelivery
    {
        $delivery = GrowthDelivery::query()->with(['campaign', 'triggerLog', 'experiment', 'user', 'messageLog'])->findOrFail($deliveryId);

        if ($delivery->scheduled_for && $delivery->scheduled_for->isFuture()) {
            return $delivery;
        }

        $campaign = $delivery->campaign;
        $user = $delivery->user;
        $payload = $delivery->payload ?? [];

        $delivery->forceFill([
            'attempts' => (int) $delivery->attempts + 1,
            'last_attempt_at' => now(),
        ])->save();

        $messageLog = $delivery->messageLog ?: GrowthMessageLog::query()->create([
            'campaign_id' => $delivery->campaign_id,
            'trigger_log_id' => $delivery->trigger_log_id,
            'delivery_id' => $delivery->id,
            'experiment_id' => $delivery->experiment_id,
            'user_id' => $delivery->user_id,
            'channel' => $delivery->channel === 'email' && ! $this->growthCampaignService->realEmailEnabled() ? 'email_simulated' : $delivery->channel,
            'status' => 'queued',
            'recipient' => $delivery->recipient,
            'subject' => $delivery->subject,
            'message' => $delivery->message,
            'coupon_code' => $payload['coupon_code'] ?? $campaign?->coupon_code,
            'experiment_variant' => $delivery->experiment_variant,
            'meta' => [
                'delivery_id' => $delivery->id,
                'provider' => $delivery->provider,
                'experiment_key' => optional($delivery->experiment)->experiment_key,
                'template_key' => $payload['template_key'] ?? $campaign?->default_template_key,
                'locale' => $payload['locale'] ?? app()->getLocale(),
                'simulated_email' => $delivery->status === 'simulated',
                'offer_label' => $payload['offer_label'] ?? null,
                'timing_reason' => $payload['timing_reason'] ?? null,
            ],
            'sent_at' => now(),
        ]);

        if (! $delivery->message_log_id) {
            $delivery->update(['message_log_id' => $messageLog->id]);
        }

        if ($delivery->channel === 'email' && ! $this->growthCampaignService->realEmailEnabled()) {
            $delivery->update([
                'status' => 'simulated',
                'sent_at' => now(),
                'meta' => array_merge($delivery->meta ?? [], ['simulation_reason' => 'real_email_disabled']),
            ]);

            $messageLog->update([
                'status' => 'simulated',
                'channel' => 'email_simulated',
                'sent_at' => now(),
            ]);

            optional($delivery->triggerLog)->update(['status' => 'sent', 'processed_at' => now()]);

            return $delivery->fresh(['campaign', 'triggerLog', 'experiment', 'user', 'messageLog']);
        }

        $result = $this->router->driverFor($delivery)->send($delivery, $user, $payload);
        $status = (string) ($result['status'] ?? 'sent');

        $delivery->update([
            'status' => $status,
            'recipient' => $result['recipient'] ?? $delivery->recipient,
            'sent_at' => in_array($status, ['sent', 'delivered'], true) ? now() : $delivery->sent_at,
            'meta' => array_merge($delivery->meta ?? [], $result['meta'] ?? []),
        ]);

        $messageLog->update([
            'status' => $status,
            'recipient' => $result['recipient'] ?? $messageLog->recipient,
            'meta' => array_merge($messageLog->meta ?? [], $result['meta'] ?? []),
            'sent_at' => now(),
        ]);

        optional($delivery->triggerLog)->update(['status' => $status === 'skipped' ? 'skipped' : 'sent', 'processed_at' => now()]);

        if (in_array($status, ['sent', 'delivered', 'simulated'], true)) {
            app(GrowthAttributionService::class)->syncForDelivery($delivery->fresh(['campaign', 'experiment', 'user']));
        }

        return $delivery->fresh(['campaign', 'triggerLog', 'experiment', 'user', 'messageLog']);
    }

    public function markFailed(int $deliveryId, \Throwable $exception): void
    {
        $delivery = GrowthDelivery::query()->with('messageLog', 'triggerLog')->find($deliveryId);

        if (! $delivery) {
            return;
        }

        $delivery->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => $exception->getMessage(),
        ]);

        if ($delivery->messageLog) {
            $delivery->messageLog->update([
                'status' => 'failed',
                'meta' => array_merge($delivery->messageLog->meta ?? [], ['error' => $exception->getMessage()]),
            ]);
        }

        optional($delivery->triggerLog)->update(['status' => 'failed', 'processed_at' => now()]);
    }
}
