<?php

namespace Tests\Feature;

use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupportLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_support_pagination_is_live_and_remains_customer_scoped(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        foreach (range(1, 21) as $number) {
            $this->supportCaseFor(
                $customer,
                'CS-LIVE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                now()->subMinutes($number)
            );
        }

        $this->supportCaseFor($other, 'CS-OTHER-CUSTOMER', now());

        $url = route('support.index', ['page' => 2]);

        $this->actingAs($customer)
            ->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-results', false)
            ->assertSee('CS-LIVE-21')
            ->assertDontSee('CS-OTHER-CUSTOMER');

        $this->withHeader('X-Live-List', '1')
            ->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('CS-LIVE-21')
            ->assertDontSee('CS-OTHER-CUSTOMER')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);
    }

    private function supportCaseFor(User $customer, string $number, $timestamp): SupportCase
    {
        $case = SupportCase::query()->create([
            'case_number' => $number,
            'customer_id' => $customer->id,
            'subject' => 'Support list test '.$number,
            'priority' => SupportCase::PRIORITY_NORMAL,
            'status' => SupportCase::STATUS_OPEN,
            'source' => 'customer_portal',
        ]);

        $case->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->save();

        return $case;
    }
}
