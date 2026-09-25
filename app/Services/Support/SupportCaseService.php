<?php

namespace App\Services\Support;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\SupportCase;
use App\Models\SupportCaseMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportCaseService
{
    public function __construct(protected SupportSlaService $slaService)
    {
    }
    public function createForCustomer(User $customer, array $payload): SupportCase
    {
        return DB::transaction(function () use ($customer, $payload) {
            $order = $this->resolveCustomerOrder($customer, $payload['order_id'] ?? null);

            $case = SupportCase::query()->create([
                'case_number' => $this->nextCaseNumber(),
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
                'subject' => trim((string) $payload['subject']),
                'category' => filled($payload['category'] ?? null) ? trim((string) $payload['category']) : null,
                'priority' => SupportCase::PRIORITY_NORMAL,
                'status' => SupportCase::STATUS_OPEN,
                'source' => 'customer_portal',
                'last_customer_message_at' => now(),
            ]);

            $this->slaService->applyToNewCase($case);

            $case->messages()->create([
                'author_user_id' => $customer->id,
                'author_type' => SupportCaseMessage::AUTHOR_CUSTOMER,
                'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
                'body' => trim((string) $payload['message']),
            ]);

            return $case->fresh(['customer', 'order', 'messages']);
        });
    }

    public function createForStaff(User $actor, array $payload): SupportCase
    {
        return DB::transaction(function () use ($actor, $payload) {
            $customerId = filled($payload['customer_id'] ?? null) ? (int) $payload['customer_id'] : null;
            $order = filled($payload['order_id'] ?? null) ? Order::query()->find((int) $payload['order_id']) : null;

            if ($customerId) {
                $customer = User::query()->findOrFail($customerId);

                if ($customer->isLegacyAdmin()) {
                    throw ValidationException::withMessages([
                        'customer_id' => __('Support customers must use customer accounts, not staff accounts.'),
                    ]);
                }
            }

            if ($order && $customerId && (int) $order->user_id !== $customerId) {
                throw ValidationException::withMessages([
                    'order_id' => __('The selected order does not belong to the selected customer.'),
                ]);
            }

            if ($order && ! $customerId) {
                $customerId = $order->user_id;
            }

            $case = SupportCase::query()->create([
                'case_number' => $this->nextCaseNumber(),
                'customer_id' => $customerId,
                'order_id' => $order?->id,
                'assigned_to_user_id' => $actor->id,
                'subject' => trim((string) $payload['subject']),
                'category' => filled($payload['category'] ?? null) ? trim((string) $payload['category']) : null,
                'priority' => $payload['priority'] ?? SupportCase::PRIORITY_NORMAL,
                'status' => SupportCase::STATUS_OPEN,
                'source' => 'admin',
                'first_response_at' => ($payload['visibility'] ?? SupportCaseMessage::VISIBILITY_CUSTOMER) === SupportCaseMessage::VISIBILITY_CUSTOMER ? now() : null,
                'last_staff_message_at' => now(),
            ]);

            $this->slaService->applyToNewCase($case);

            $case->messages()->create([
                'author_user_id' => $actor->id,
                'author_type' => SupportCaseMessage::AUTHOR_STAFF,
                'visibility' => $payload['visibility'] ?? SupportCaseMessage::VISIBILITY_CUSTOMER,
                'body' => trim((string) $payload['message']),
            ]);

            $this->audit($actor, $case, 'support_case_created', [
                'source' => 'admin',
                'priority' => $case->priority,
            ]);

            return $case->fresh(['customer', 'order', 'assignee', 'messages']);
        });
    }

    public function addCustomerReply(SupportCase $case, User $customer, string $body): SupportCaseMessage
    {
        if ((int) $case->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($case->status === SupportCase::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'message' => __('Closed support cases cannot receive new replies.'),
            ]);
        }

        return DB::transaction(function () use ($case, $customer, $body) {
            $message = $case->messages()->create([
                'author_user_id' => $customer->id,
                'author_type' => SupportCaseMessage::AUTHOR_CUSTOMER,
                'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
                'body' => trim($body),
            ]);

            $case->update([
                'status' => SupportCase::STATUS_OPEN,
                'last_customer_message_at' => now(),
                'resolved_at' => null,
            ]);

            return $message;
        });
    }

    public function addStaffMessage(SupportCase $case, User $actor, string $body, string $visibility): SupportCaseMessage
    {
        return DB::transaction(function () use ($case, $actor, $body, $visibility) {
            $message = $case->messages()->create([
                'author_user_id' => $actor->id,
                'author_type' => SupportCaseMessage::AUTHOR_STAFF,
                'visibility' => $visibility,
                'body' => trim($body),
            ]);

            $changes = ['last_staff_message_at' => now()];

            if (! $case->first_response_at && $visibility === SupportCaseMessage::VISIBILITY_CUSTOMER) {
                $changes['first_response_at'] = now();
            }

            if ($visibility === SupportCaseMessage::VISIBILITY_CUSTOMER
                && in_array($case->status, [SupportCase::STATUS_OPEN, SupportCase::STATUS_PENDING_TEAM], true)) {
                $changes['status'] = SupportCase::STATUS_PENDING_CUSTOMER;
            }

            $case->update($changes);

            $this->audit($actor, $case, $visibility === SupportCaseMessage::VISIBILITY_INTERNAL
                ? 'support_internal_note_added'
                : 'support_customer_reply_added');

            return $message;
        });
    }

    public function updateCase(SupportCase $case, User $actor, array $payload): SupportCase
    {
        return DB::transaction(function () use ($case, $actor, $payload) {
            $originalPriority = $case->priority;

            if (array_key_exists('assigned_to_user_id', $payload) && filled($payload['assigned_to_user_id'])) {
                $assignee = User::query()->findOrFail((int) $payload['assigned_to_user_id']);

                if (! $assignee->isLegacyAdmin() || ! $assignee->hasPermission('support.view')) {
                    throw ValidationException::withMessages([
                        'assigned_to_user_id' => __('Support cases can only be assigned to staff members who can view support.'),
                    ]);
                }
            }

            $changes = [
                'priority' => $payload['priority'] ?? $case->priority,
                'status' => $payload['status'] ?? $case->status,
                'assigned_to_user_id' => filled($payload['assigned_to_user_id'] ?? null)
                    ? (int) $payload['assigned_to_user_id']
                    : null,
            ];

            if ($changes['status'] === SupportCase::STATUS_RESOLVED && ! $case->resolved_at) {
                $changes['resolved_at'] = now();
                $changes['closed_at'] = null;
            } elseif ($changes['status'] === SupportCase::STATUS_CLOSED) {
                $changes['closed_at'] = $case->closed_at ?: now();
            } else {
                $changes['closed_at'] = null;

                if ($changes['status'] !== SupportCase::STATUS_RESOLVED) {
                    $changes['resolved_at'] = null;
                }
            }

            $case->update($changes);

            if ($case->priority !== $originalPriority) {
                $this->slaService->recalculateForPriority($case);
            }

            $this->audit($actor, $case, 'support_case_updated', [
                'status' => $case->status,
                'priority' => $case->priority,
                'assigned_to_user_id' => $case->assigned_to_user_id,
            ]);

            return $case->fresh(['customer', 'order', 'assignee']);
        });
    }

    private function resolveCustomerOrder(User $customer, mixed $orderId): ?Order
    {
        if (! filled($orderId)) {
            return null;
        }

        $order = Order::query()
            ->whereKey((int) $orderId)
            ->where('user_id', $customer->id)
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => __('The selected order is not available for this account.'),
            ]);
        }

        return $order;
    }

    private function nextCaseNumber(): string
    {
        do {
            $number = 'CS-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (SupportCase::query()->where('case_number', $number)->exists());

        return $number;
    }

    private function audit(User $actor, SupportCase $case, string $action, array $meta = []): void
    {
        AdminActivityLog::query()->create([
            'admin_user_id' => $actor->id,
            'type' => 'support',
            'action' => $action,
            'description' => $case->case_number.' · '.$case->subject,
            'subject_type' => SupportCase::class,
            'subject_id' => $case->id,
            'meta' => $meta,
        ]);
    }
}
