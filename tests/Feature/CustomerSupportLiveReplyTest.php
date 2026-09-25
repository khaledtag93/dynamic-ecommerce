<?php

namespace Tests\Feature;

use App\Models\SupportCase;
use App\Models\SupportCaseMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupportLiveReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_reply_can_return_live_message_and_reopen_resolved_case(): void
    {
        $customer = User::factory()->create();
        $case = $this->caseFor($customer, SupportCase::STATUS_RESOLVED);
        $case->forceFill(['resolved_at' => now()])->save();

        $response = $this->actingAs($customer)
            ->postJson(route('support.reply', $case), [
                'message' => 'The issue is still happening.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('reply.body', 'The issue is still happening.')
            ->assertJsonPath('reply.author_label', __('You'))
            ->assertJsonPath('case.status', SupportCase::STATUS_OPEN)
            ->assertJsonPath('case.status_label', __('Open'))
            ->assertJsonPath('message', __('Your reply was added.'));

        $this->assertDatabaseHas('support_case_messages', [
            'support_case_id' => $case->id,
            'author_user_id' => $customer->id,
            'author_type' => SupportCaseMessage::AUTHOR_CUSTOMER,
            'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
            'body' => 'The issue is still happening.',
        ]);

        $case->refresh();
        $this->assertSame(SupportCase::STATUS_OPEN, $case->status);
        $this->assertNull($case->resolved_at);
    }

    public function test_live_reply_keeps_customer_ownership_and_closed_case_rules(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $openCase = $this->caseFor($owner, SupportCase::STATUS_OPEN);

        $this->actingAs($other)
            ->postJson(route('support.reply', $openCase), ['message' => 'Not mine'])
            ->assertNotFound();

        $closedCase = $this->caseFor($owner, SupportCase::STATUS_CLOSED);

        $this->actingAs($owner)
            ->postJson(route('support.reply', $closedCase), ['message' => 'Too late'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertDatabaseMissing('support_case_messages', [
            'support_case_id' => $closedCase->id,
            'body' => 'Too late',
        ]);
    }

    public function test_support_detail_keeps_progressive_reply_hooks_and_arabic_failure_copy(): void
    {
        $customer = User::factory()->create();
        $case = $this->caseFor($customer, SupportCase::STATUS_OPEN);

        $this->actingAs($customer)
            ->get(route('support.show', $case))
            ->assertOk()
            ->assertSee('data-support-reply-form', false)
            ->assertSee('data-support-message-list', false)
            ->assertSee('data-support-case-status', false)
            ->assertSee('data-support-last-updated', false)
            ->assertSee('X-Support-Live', false);

        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(
            'تعذر إرسال ردك. حاول مرة أخرى.',
            $arabic['Could not send your reply. Please try again.'] ?? null
        );
    }

    private function caseFor(User $customer, string $status): SupportCase
    {
        return SupportCase::query()->create([
            'case_number' => 'CS-LIVE-'.uniqid(),
            'customer_id' => $customer->id,
            'subject' => 'Customer live reply test',
            'priority' => SupportCase::PRIORITY_NORMAL,
            'status' => $status,
            'source' => 'customer_portal',
        ]);
    }
}
