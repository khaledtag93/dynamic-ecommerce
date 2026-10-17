<?php

namespace Tests\Feature;

use App\Models\GrowthDelivery;
use App\Models\NotificationAutomationRule;
use App\Models\NotificationDispatchLog;
use App\Models\Order;
use App\Services\Commerce\NotificationAutomationService;
use App\Services\Commerce\OrderNotificationService;
use App\Services\Growth\Channels\InAppGrowthChannel;
use App\Services\Growth\Channels\SmtpEmailGrowthChannel;
use App\Services\Growth\GrowthDeliveryRouter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Isolated source-only routing boundaries. NEVER call the real automation
 * sendUsingChannel(), GrowthDeliveryService::send(), job handle(), retry(),
 * scheduler or scanner. Mail and notifications are both faked as a backstop.
 */
class OutboundAutomationRoutingBoundaryTest extends TestCase
{
    public function test_automation_retry_and_fallback_pass_expected_channel_to_a_non_sending_leaf(): void
    {
        Mail::fake();
        Notification::fake();

        // The only overridden leaf records arguments; it cannot send messages,
        // create delivery logs or access external providers.
        $service = new class extends NotificationAutomationService {
            public array $captured = [];

            public function __construct()
            {
                // No application services are initialized for the route-only fake.
            }

            protected function sendUsingChannel(
                Order $order,
                string $event,
                string $channel,
                string $message,
                string $title,
                NotificationAutomationRule $rule,
                bool $manual = false
            ): string {
                $this->captured[] = [
                    'channel' => $channel,
                    'event' => $event,
                    'message' => $message,
                    'title' => $title,
                    'rule' => $rule->action_type,
                    'manual' => $manual,
                    'order' => $order,
                ];

                return 'intercepted_no_send';
            }
        };

        $order = new Order(['order_number' => 'ROUTE-ONLY-TEST']);
        $log = new NotificationDispatchLog([
            'event' => OrderNotificationService::EVENT_CANCELLED,
            'channel' => 'database',
            'message' => 'Synthetic message',
            'title' => 'Synthetic title',
        ]);

        $method = new ReflectionMethod(NotificationAutomationService::class, 'applyRule');

        $fallback = new NotificationAutomationRule([
            'action_type' => NotificationAutomationRule::ACTION_FALLBACK_CHANNEL,
            'target_channel' => 'email',
        ]);
        $retry = new NotificationAutomationRule([
            'action_type' => NotificationAutomationRule::ACTION_RETRY_SAME_CHANNEL,
            'target_channel' => 'whatsapp',
        ]);

        $this->assertSame('intercepted_no_send', $method->invoke($service, $fallback, $log, $order, false));
        $this->assertSame('intercepted_no_send', $method->invoke($service, $retry, $log, $order, true));

        $this->assertCount(2, $service->captured);
        $this->assertSame(['email', 'database'], array_column($service->captured, 'channel'));
        $this->assertSame([false, true], array_column($service->captured, 'manual'));
        $this->assertSame([OrderNotificationService::EVENT_CANCELLED, OrderNotificationService::EVENT_CANCELLED], array_column($service->captured, 'event'));
        $this->assertSame($order, $service->captured[0]['order']);
        $this->assertSame($order, $service->captured[1]['order']);

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_growth_router_resolves_existing_driver_classes_without_calling_send(): void
    {
        Mail::fake();
        Notification::fake();

        $router = new GrowthDeliveryRouter();

        foreach ([null, 'smtp'] as $provider) {
            $delivery = new GrowthDelivery(['channel' => 'email', 'provider' => $provider]);
            $this->assertInstanceOf(SmtpEmailGrowthChannel::class, $router->driverFor($delivery));
        }

        $this->assertInstanceOf(
            InAppGrowthChannel::class,
            $router->driverFor(new GrowthDelivery(['channel' => 'in_app', 'provider' => 'database']))
        );

        foreach ([
            ['channel' => 'email', 'provider' => 'qas_reset_capture'],
            ['channel' => 'email', 'provider' => 'unsupported'],
            ['channel' => 'whatsapp', 'provider' => 'smtp'],
        ] as $attributes) {
            try {
                $router->driverFor(new GrowthDelivery($attributes));
                $this->fail('Unconfigured Growth delivery channels must not resolve a driver.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Unsupported growth delivery channel/provider combination.', $e->getMessage());
            }
        }

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }
}
