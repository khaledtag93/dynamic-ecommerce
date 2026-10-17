<?php

namespace App\Support;

final class NotificationFailureSummary
{
    /**
     * Present only allowlisted guidance, never original exception messages,
     * paths, provider responses, tokens, or customer data in the admin UI.
     * The underlying stored diagnostic information is left untouched.
     */
    public static function forOperator(?string $error, ?string $status = null): string
    {
        if ($status === 'skipped') {
            return __('Notification was skipped. Check channel settings before retrying.');
        }

        $source = mb_strtolower((string) $error);

        if (str_contains($source, 'htmlspecialchars') || str_contains($source, 'viewexception')) {
            return __('Notification content could not be rendered. Check the template before retrying.');
        }
        if (str_contains($source, 'timed out') || str_contains($source, 'timeout')) {
            return __('Delivery timed out. Check provider availability before retrying.');
        }
        if (str_contains($source, 'rate limit') || str_contains($source, 'too many requests')) {
            return __('Provider rate limit reached. Try again later.');
        }
        if (str_contains($source, 'recipient') || str_contains($source, 'invalid phone')) {
            return __('Recipient details need review before retrying.');
        }

        return __('Delivery failed. Review protected system logs before retrying.');
    }
}
