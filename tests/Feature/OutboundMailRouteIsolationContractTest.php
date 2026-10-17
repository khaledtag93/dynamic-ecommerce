<?php

namespace Tests\Feature;

use App\Mail\GrowthCampaignMail;
use App\Mail\OrderUpdateMail;
use App\Models\GrowthDelivery;
use App\Models\NotificationAutomationRule;
use App\Models\NotificationDispatchLog;
use App\Models\Order;
use App\Services\Commerce\NotificationAutomationService;
use App\Services\Commerce\OrderNotificationService;
use App\Services\Growth\Channels\SmtpEmailGrowthChannel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Audit only existing, directly callable mail leaves, not scanner/retry/queue
 * entrypoints. Every transport call is intercepted. No provider or live data.
 *
 * A successful fake "sent" result is NOT QAS delivery acceptance and cannot
 * establish safety of a hypothetical qas_reset_capture mailer.
 */
class OutboundMailRouteIsolationContractTest extends TestCase
{
    public function test_order_email_leaf_uses_global_fake_and_not_password_reset_named_mailer(): void
    {
        Mail::fake();
        Notification::fake();

        $this->assertSame('array', config('mail.default'));
        $this->assertNull(config('mail.mailers.qas_reset_capture'));

        $order = new Order([
            'order_number' => 'FAKE-ORDER-001',
            'customer_email' => 'order-only@example.test',
        ]);

        $method = new ReflectionMethod(OrderNotificationService::class, 'sendEmail');
        $this->assertTrue($method->invoke(
            app(OrderNotificationService::class),
            OrderNotificationService::EVENT_STATUS_UPDATED,
            $order,
            'Synthetic order title',
            'Synthetic order message'
        ));

        Mail::assertSent(OrderUpdateMail::class, function (OrderUpdateMail $mail) use ($order): bool {
            return $mail->hasTo('order-only@example.test')
                && $mail->order === $order
                && $mail->mailer === null;
        });
        Mail::assertSentCount(1);
        Mail::assertNotQueued(OrderUpdateMail::class);
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_order_email_leaf_does_not_send_when_recipient_is_absent(): void
    {
        Mail::fake();
        Notification::fake();

        $order = new Order(['order_number' => 'FAKE-ORDER-NO-EMAIL']);
        $method = new ReflectionMethod(OrderNotificationService::class, 'sendEmail');

        $this->assertFalse($method->invoke(
            app(OrderNotificationService::class),
            OrderNotificationService::EVENT_STATUS_UPDATED,
            $order,
            'Synthetic title',
            'Synthetic message'
        ));

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_admin_escalation_raw_mail_is_intercepted_without_dispatching_or_scanning(): void
    {
        Mail::fake();
        Notification::fake();

        $captured = null;

        // Laravel MailFake::raw() is a no-op and NOT an assertSent record.
        // Replace just that facade method with a zero-network mock, and run
        // the callback against an in-memory message double.
        Mail::shouldReceive('mailer')->never();
        Mail::shouldReceive('raw')->once()
            ->withArgs(function ($body, $callback) use (&$captured): bool {
                $message = new class {
                    public ?string $to = null;
                    public ?string $subject = null;

                    public function to(string $email): static
                    {
                        $this->to = $email;

                        return $this;
                    }

                    public function subject(string $subject): static
                    {
                        $this->subject = $subject;

                        return $this;
                    }
                };

                $callback($message);
                $captured = [
                    'body' => $body,
                    'to' => $message->to,
                    'subject' => $message->subject,
                ];

                return is_string($body)
                    && $message->to === 'operator-only@example.test'
                    && is_string($message->subject)
                    && str_contains($body, 'FAKE-ORDER-ADMIN');
            })
            ->andReturnNull();

        $order = new Order(['order_number' => 'FAKE-ORDER-ADMIN']);
        $rule = new NotificationAutomationRule([
            'name' => 'Synthetic mail escalation',
            'admin_email' => 'operator-only@example.test',
            'action_type' => NotificationAutomationRule::ACTION_NOTIFY_ADMIN_EMAIL,
        ]);
        $log = new NotificationDispatchLog([
            'event' => OrderNotificationService::EVENT_CANCELLED,
            'channel' => 'email',
            'status' => NotificationDispatchLog::STATUS_FAILED,
            'error_message' => 'Synthetic failure only',
        ]);

        // Invoke ONLY this protected mail leaf, not executeForDispatch(),
        // sendUsingChannel(), a scanner, an admin retry or any queue worker.
        $method = new ReflectionMethod(NotificationAutomationService::class, 'notifyAdminEmail');
        $result = $method->invoke(
            app(NotificationAutomationService::class),
            $rule,
            $log,
            $order
        );

        $this->assertSame('admin_emailed', $result);
        $this->assertSame('operator-only@example.test', $captured['to']);
        $this->assertStringContainsString('FAKE-ORDER-ADMIN', $captured['subject']);
        $this->assertSame('array', config('mail.default'));
        Notification::assertNothingSent();
    }

    public function test_growth_email_driver_uses_global_fake_and_missing_recipient_skips_without_send(): void
    {
        Mail::fake();
        Notification::fake();

        $driver = app(SmtpEmailGrowthChannel::class);
        $valid = new GrowthDelivery(['recipient' => 'growth-only@example.test']);

        $result = $driver->send($valid, null, [
            'subject' => 'Synthetic growth title',
            'message' => 'Synthetic growth body',
        ]);

        // "sent" is the driver's return contract only; transport is faked.
        $this->assertSame('sent', $result['status']);
        $this->assertSame('growth-only@example.test', $result['recipient']);
        Mail::assertSent(GrowthCampaignMail::class, function (GrowthCampaignMail $mail): bool {
            return $mail->hasTo('growth-only@example.test')
                && $mail->mailer === null
                && ($mail->payload['subject'] ?? null) === 'Synthetic growth title';
        });
        Mail::assertSentCount(1);
        Mail::assertNotQueued(GrowthCampaignMail::class);

        $missing = $driver->send(new GrowthDelivery(['recipient' => '']), null, []);
        $this->assertSame('skipped', $missing['status']);
        $this->assertSame('missing_email', $missing['meta']['reason']);
        Mail::assertSentCount(1);
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }
}
