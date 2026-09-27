<?php

namespace Tests\Feature;

use App\Models\NotificationDispatchLog;
use App\Models\Order;
use App\Services\Commerce\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;
    public function test_notification_dispatch_search_is_bounded_and_escaped(): void
    {
        $model = file_get_contents(app_path('Models/NotificationDispatchLog.php'));

        $this->assertStringContainsString(
            'mb_substr(trim((string) $search), 0, 100)',
            $model
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $model);
        $this->assertStringNotContainsString('"%{$search}%"', $model);
    }

    public function test_missing_notification_recipients_are_skipped_instead_of_marked_sent(): void
    {
        $order = Order::query()->create([
            'order_number' => 'NOTIFY-NO-RECIPIENT',
            'customer_name' => 'No Recipient',
            'customer_email' => '',
            'customer_phone' => '',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
        ]);

        $service = app(OrderNotificationService::class);

        foreach (['database', 'email', 'whatsapp'] as $channel) {
            $service->dispatch(OrderNotificationService::EVENT_STATUS_UPDATED, $order, [
                'channels' => [$channel],
                'title' => 'Status update',
                'message' => 'Order status changed.',
            ]);
        }

        $logs = NotificationDispatchLog::query()
            ->where('order_id', $order->id)
            ->whereIn('channel', ['database', 'email', 'whatsapp'])
            ->get()
            ->keyBy('channel');

        $this->assertCount(3, $logs);

        foreach (['database', 'email', 'whatsapp'] as $channel) {
            $this->assertSame(NotificationDispatchLog::STATUS_SKIPPED, $logs[$channel]->status);
            $this->assertNull($logs[$channel]->sent_at);
            $this->assertSame(
                __('Recipient is missing or the channel is reserved for later.'),
                $logs[$channel]->error_message
            );
        }
    }

    public function test_notification_center_navigation_exposes_current_page_semantics(): void
    {
        $layout = file_get_contents(resource_path('views/admin/settings/notification-center/layout.blade.php'));

        $this->assertStringContainsString('aria-current="page"', $layout);
        $this->assertStringContainsString('($currentSection ?? \'overview\') === $key', $layout);
    }
}
