<?php

namespace Tests\Feature;

use App\Models\GrowthCampaign;
use App\Models\GrowthDelivery;
use App\Models\GrowthTriggerLog;
use App\Models\User;
use App\Services\Growth\GrowthCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;
use Tests\TestCase;

/**
 * CI-only, synthetic MySQL read contracts. NEVER invoke processDuePending,
 * Growth jobs/queues, send, retry, scheduler/scanner or a provider.
 * Selection duplicates only the SOURCE SELECT, not dispatch functionality.
 */
class GrowthDueEligibilityAndCooldownReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_due_selector_can_fill_a_bounded_page_with_older_simulated_rows(): void
    {
        Mail::fake();
        Notification::fake();

        $source = file_get_contents(app_path('Services/Growth/GrowthDeliveryService.php'));
        $start = strpos($source, 'public function processDuePending(');
        $end = strpos($source, 'public function send(', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $selector = substr($source, $start, $end - $start);
        // Anchor this CI-only mirrored read to the exact existing source
        // selector; do not call the production method (it dispatches jobs).
        $this->assertStringContainsString("->whereIn('status', ['pending', 'scheduled', 'simulated'])", $selector);
        $this->assertStringContainsString("->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now())", $selector);
        $this->assertStringContainsString("->orderBy('scheduled_for')", $selector);
        $this->assertStringContainsString('->limit($limit)', $selector);

        $oldSimulated = $this->delivery('simulated', now()->subDays(10));
        $pending = $this->delivery('pending', now()->subDays(9));
        $scheduledDue = $this->delivery('scheduled', now()->subDays(8));
        $this->delivery('sent', now()->subDays(11));
        $this->delivery('scheduled', now()->addDay());

        // Read-only *mirror* of the SELECT above; no job dispatch or delivery
        // method is invoked. This is eligibility evidence, not an acceptance
        // test for execution, throughput or provider behavior.
        $select = static fn (int $limit): array => GrowthDelivery::query()
            ->whereIn('status', ['pending', 'scheduled', 'simulated'])
            ->where(function ($query) {
                $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $this->assertSame([$oldSimulated->id], $select(1));
        $this->assertSame([$oldSimulated->id, $pending->id], $select(2));
        $this->assertSame([$oldSimulated->id, $pending->id, $scheduledDue->id], $select(10));

        // All fixtures and checks live only in CI's RefreshDatabase.
        $this->assertSame(5, GrowthDelivery::query()->count());
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_trigger_cooldown_is_campaign_and_actor_scoped_not_delivery_idempotency(): void
    {
        Mail::fake();
        Notification::fake();

        $campaign = GrowthCampaign::query()->create([
            'name' => 'Synthetic cooldown contract',
            'campaign_key' => 'ci_source_cooldown_guard',
            'campaign_type' => 'retention',
            'trigger_event' => 'ci_source_event',
            'channel' => 'email',
            'config' => ['cooldown_hours' => 24],
            'is_active' => true,
        ]);

        $recentUser = User::factory()->create();
        $simulatedOnlyUser = User::factory()->create();
        $oldUser = User::factory()->create();

        $this->trigger($campaign, $recentUser->id, null, 'sent', now()->subMinutes(5));
        $this->trigger($campaign, $simulatedOnlyUser->id, null, 'simulated', now()->subMinutes(5));
        $this->trigger($campaign, $oldUser->id, null, 'sent', now()->subDays(2));
        $this->trigger($campaign, null, 'ci-session-a', 'triggered', now()->subMinutes(2));

        $service = app(GrowthCampaignService::class);
        $method = new ReflectionMethod(GrowthCampaignService::class, 'recentlyTriggered');

        $this->assertTrue($method->invoke($service, $campaign, ['user_id' => $recentUser->id]));
        $this->assertFalse($method->invoke($service, $campaign, ['user_id' => $simulatedOnlyUser->id]));
        $this->assertFalse($method->invoke($service, $campaign, ['user_id' => $oldUser->id]));
        $this->assertTrue($method->invoke($service, $campaign, ['session_id' => 'ci-session-a']));
        $this->assertFalse($method->invoke($service, $campaign, ['session_id' => 'ci-session-b']));
        $this->assertFalse($method->invoke($service, $campaign, []));

        // This only calls an existing READ query via reflection. It neither
        // creates new triggers nor exercises GrowthDeliveryService::send.
        $this->assertSame(4, GrowthTriggerLog::query()->where('campaign_id', $campaign->id)->count());
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    private function delivery(string $status, \DateTimeInterface $scheduledFor): GrowthDelivery
    {
        return GrowthDelivery::query()->create([
            'channel' => 'email',
            'provider' => 'smtp',
            'status' => $status,
            'recipient' => 'ci-only@example.test',
            'scheduled_for' => $scheduledFor,
        ]);
    }

    private function trigger(
        GrowthCampaign $campaign,
        ?int $userId,
        ?string $sessionId,
        string $status,
        \DateTimeInterface $triggeredAt
    ): void {
        GrowthTriggerLog::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'status' => $status,
            'trigger_event' => 'ci_source_event',
            'channel' => 'email',
            'triggered_at' => $triggeredAt,
        ]);
    }
}
