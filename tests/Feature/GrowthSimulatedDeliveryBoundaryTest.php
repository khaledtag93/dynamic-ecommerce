<?php

namespace Tests\Feature;

use App\Models\GrowthDelivery;
use App\Models\GrowthMessageLog;
use App\Services\Growth\GrowthCampaignService;
use App\Services\Growth\GrowthDeliveryRouter;
use App\Services\Growth\GrowthDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Mockery;
use Tests\TestCase;

/**
 * CI-only source contracts. Calls only GrowthDeliveryService::send() directly.
 * No job handle, queue, scanner, retry, cron, router driver send or provider.
 * The router is always a blocking mock; all DB data is synthetic/transactional.
 * Assertions describe CURRENT behavior, not an approved replay policy.
 */
class GrowthSimulatedDeliveryBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulated_delivery_with_real_email_disabled_stays_internal_on_duplicate_direct_calls(): void
    {
        Mail::fake();
        Notification::fake();

        $growth = Mockery::mock(GrowthCampaignService::class);
        $growth->shouldReceive('realEmailEnabled')->andReturn(false);

        $router = Mockery::mock(GrowthDeliveryRouter::class);
        $router->shouldNotReceive('driverFor');

        $delivery = $this->syntheticEmailDelivery('simulated');
        $service = new GrowthDeliveryService($growth, $router);

        $first = $service->send($delivery->id);

        $this->assertSame('simulated', $first->status);
        $this->assertSame(1, $first->attempts);
        $this->assertSame('simulated', $first->messageLog->status);
        $this->assertSame('email_simulated', $first->messageLog->channel);
        $this->assertSame('real_email_disabled', $first->meta['simulation_reason'] ?? null);

        // This is a second direct service call, NOT a queued job or retry command.
        // Current code re-counts attempts despite reusing the same message log.
        $second = $service->send($delivery->id);

        $this->assertSame('simulated', $second->status);
        $this->assertSame(2, $second->attempts);
        $this->assertSame($first->message_log_id, $second->message_log_id);
        $this->assertSame(1, GrowthMessageLog::query()->where('delivery_id', $delivery->id)->count());

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_existing_simulated_or_sent_delivery_with_flag_on_reaches_router_boundary_before_any_provider(): void
    {
        Mail::fake();
        Notification::fake();

        foreach (['simulated', 'sent'] as $initialStatus) {
            $delivery = $this->syntheticEmailDelivery($initialStatus);

            $growth = Mockery::mock(GrowthCampaignService::class);
            $growth->shouldReceive('realEmailEnabled')->andReturn(true);

            $router = Mockery::mock(GrowthDeliveryRouter::class);
            $router->shouldReceive('driverFor')
                ->once()
                ->with(Mockery::on(
                    fn (GrowthDelivery $candidate): bool => $candidate->is($delivery)
                ))
                // Hard stop: no driver object is returned and send() is never called.
                ->andThrow(new LogicException('ROUTER_HARD_STOP_NO_PROVIDER'));

            $service = new GrowthDeliveryService($growth, $router);

            try {
                $service->send($delivery->id);
                $this->fail('The in-memory hard-stop should intercept the router boundary.');
            } catch (LogicException $exception) {
                $this->assertSame('ROUTER_HARD_STOP_NO_PROVIDER', $exception->getMessage());
            }

            // Source re-entry is observed; no real delivery status is inferred
            // because the test deliberately stops before external transport.
            $this->assertSame(1, $delivery->fresh()->attempts);
            $this->assertSame($initialStatus, $delivery->fresh()->status);
        }

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_future_scheduled_delivery_returns_before_attempts_or_router_even_if_real_email_is_enabled(): void
    {
        Mail::fake();
        Notification::fake();

        $growth = Mockery::mock(GrowthCampaignService::class);
        $growth->shouldNotReceive('realEmailEnabled');

        $router = Mockery::mock(GrowthDeliveryRouter::class);
        $router->shouldNotReceive('driverFor');

        $delivery = $this->syntheticEmailDelivery('scheduled', true);
        $result = (new GrowthDeliveryService($growth, $router))->send($delivery->id);

        $this->assertSame('scheduled', $result->status);
        $this->assertSame(0, $result->attempts);
        $this->assertNull($result->message_log_id);
        $this->assertSame(0, GrowthMessageLog::query()->where('delivery_id', $delivery->id)->count());
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }

    private function syntheticEmailDelivery(string $status, bool $future = false): GrowthDelivery
    {
        return GrowthDelivery::query()->create([
            'channel' => 'email',
            'provider' => 'smtp',
            'status' => $status,
            'recipient' => 'no-delivery@example.test',
            'subject' => 'Synthetic CI-only subject',
            'message' => 'Synthetic CI-only message',
            'payload' => ['subject' => 'Synthetic CI-only subject', 'message' => 'Synthetic CI-only message'],
            'attempts' => 0,
            'scheduled_for' => $future ? now()->addDay() : now()->subMinute(),
        ]);
    }
}
