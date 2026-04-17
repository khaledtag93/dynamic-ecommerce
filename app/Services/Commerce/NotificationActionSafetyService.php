<?php

namespace App\Services\Commerce;

use App\Models\NotificationDispatchLog;
use App\Models\WhatsAppLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationActionSafetyService
{
    public function inspectDispatchRetry(NotificationDispatchLog $log): array
    {
        if (! $log->order) {
            return $this->deny('missing_order', __('The related order is missing, so this dispatch cannot be retried safely.'));
        }

        if (! in_array($log->status, [NotificationDispatchLog::STATUS_FAILED, NotificationDispatchLog::STATUS_SKIPPED], true)) {
            return $this->deny('invalid_status', __('Only failed or skipped dispatch logs can be retried safely.'));
        }

        if ($log->retried_at && $log->retried_at->gte(now()->subMinutes(2))) {
            return $this->deny('cooldown_active', __('A retry was already requested very recently for this dispatch log.'));
        }

        $retryCount = (int) data_get($log->meta, 'retry_count', 0);
        if ($retryCount >= 3) {
            return $this->deny('retry_limit_reached', __('This dispatch log already reached the safe retry limit for operator-triggered sends.'));
        }

        if (Schema::hasTable('notification_dispatch_logs')) {
            $activeRetry = NotificationDispatchLog::query()
                ->where('retry_of_id', $log->id)
                ->whereIn('status', [NotificationDispatchLog::STATUS_PENDING, NotificationDispatchLog::STATUS_SENT])
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();

            if ($activeRetry) {
                return $this->deny('retry_already_active', __('A newer retry attempt is already active for this dispatch log.'));
            }
        }

        return $this->allow([
            'reason_code' => 'dispatch_retry_allowed',
        ]);
    }

    public function inspectWhatsAppRetry(WhatsAppLog $log): array
    {
        if (! $log->order) {
            return $this->deny('missing_order', __('The related order is missing, so this WhatsApp log cannot be retried safely.'));
        }

        if (! in_array($log->status, [WhatsAppLog::STATUS_FAILED, WhatsAppLog::STATUS_SKIPPED], true)) {
            return $this->deny('invalid_status', __('Only failed or skipped WhatsApp logs can be retried safely.'));
        }

        $meta = (array) ($log->meta ?? []);
        $lastRetryRequestedAt = data_get($meta, 'last_retry_requested_at');

        if ($lastRetryRequestedAt) {
            try {
                if (Carbon::parse((string) $lastRetryRequestedAt)->gte(now()->subMinutes(2))) {
                    return $this->deny('cooldown_active', __('A WhatsApp retry was already requested very recently for this log.'));
                }
            } catch (Throwable) {
                // Ignore malformed timestamps and continue with the remaining guards.
            }
        }

        if ((int) ($log->attempts ?? 0) >= 3) {
            return $this->deny('retry_limit_reached', __('This WhatsApp log already reached the safe retry limit for manual retries.'));
        }

        if (Schema::hasTable('whatsapp_logs')) {
            $activeRetry = WhatsAppLog::query()
                ->where('order_id', $log->order_id)
                ->where('message_type', $log->message_type)
                ->whereIn('status', [WhatsAppLog::STATUS_PENDING, WhatsAppLog::STATUS_SENT])
                ->where('created_at', '>=', now()->subMinutes(5))
                ->where(function ($query) use ($log) {
                    $query->where('meta->retry_of_log_id', $log->id)
                        ->orWhere('id', '>', $log->id);
                })
                ->exists();

            if ($activeRetry) {
                return $this->deny('retry_already_active', __('A newer WhatsApp attempt is already active for this order event.'));
            }
        }

        return $this->allow([
            'reason_code' => 'whatsapp_retry_allowed',
        ]);
    }

    public function inspectFailedQueueRetry(string $identifier): array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return $this->deny('missing_identifier', __('Missing failed queue job identifier.'));
        }

        if (! Schema::hasTable('failed_jobs')) {
            return $this->deny('missing_failed_jobs_table', __('The failed jobs table is not available.'));
        }

        $job = DB::table('failed_jobs')
            ->select(['id', 'uuid', 'queue', 'connection', 'failed_at'])
            ->where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (! $job) {
            return $this->deny('job_not_found', __('The selected failed queue job no longer exists.'));
        }

        return $this->allow([
            'reason_code' => 'queue_retry_allowed',
            'job' => [
                'id' => $job->id ?? null,
                'uuid' => $job->uuid ?? null,
                'queue' => $job->queue ?? null,
                'connection' => $job->connection ?? null,
                'failed_at' => $job->failed_at ?? null,
            ],
        ]);
    }

    protected function allow(array $extra = []): array
    {
        return array_merge([
            'allowed' => true,
            'message' => null,
        ], $extra);
    }

    protected function deny(string $reasonCode, string $message): array
    {
        return [
            'allowed' => false,
            'reason_code' => $reasonCode,
            'message' => $message,
        ];
    }
}
