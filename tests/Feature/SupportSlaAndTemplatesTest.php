<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SupportCase;
use App\Models\SupportCaseMessage;
use App\Models\SupportReplyTemplate;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Auth\AuthorizationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportSlaAndTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_new_case_receives_priority_sla_deadlines_and_priority_change_recalculates_open_targets(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        WebsiteSetting::setValue('support_sla_first_response_normal_hours', '6', 'support', 'integer');
        WebsiteSetting::setValue('support_sla_resolution_normal_hours', '48', 'support', 'integer');
        WebsiteSetting::setValue('support_sla_first_response_urgent_hours', '1', 'support', 'integer');
        WebsiteSetting::setValue('support_sla_resolution_urgent_hours', '8', 'support', 'integer');

        $customer = User::factory()->create(['role_as' => 0]);

        $this->actingAs($customer)
            ->post(route('support.store'), [
                'subject' => 'SLA test',
                'message' => 'Please help.',
            ])
            ->assertRedirect();

        $case = SupportCase::query()->firstOrFail();

        $this->assertSame('2026-09-25 18:00:00', $case->first_response_due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-27 12:00:00', $case->resolution_due_at->format('Y-m-d H:i:s'));

        $agent = $this->staffWithRole('support_agent');

        $this->actingAs($agent)
            ->patch(route('admin.support.update', $case), [
                'status' => SupportCase::STATUS_OPEN,
                'priority' => SupportCase::PRIORITY_URGENT,
                'assigned_to_user_id' => $agent->id,
            ])
            ->assertRedirect();

        $case->refresh();

        $this->assertSame('2026-09-25 13:00:00', $case->first_response_due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-25 20:00:00', $case->resolution_due_at->format('Y-m-d H:i:s'));
    }

    public function test_sla_state_tracks_met_and_breached_without_counting_internal_notes_as_first_response(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        app(AuthorizationService::class)->syncDefaults();

        WebsiteSetting::setValue('support_sla_first_response_normal_hours', '1', 'support', 'integer');

        $customer = User::factory()->create(['role_as' => 0]);

        $this->actingAs($customer)->post(route('support.store'), [
            'subject' => 'First response test',
            'message' => 'Customer message',
        ]);

        $case = SupportCase::query()->firstOrFail();
        $agent = $this->staffWithRole('support_agent');

        $this->actingAs($agent)->post(route('admin.support.reply', $case), [
            'visibility' => SupportCaseMessage::VISIBILITY_INTERNAL,
            'message' => 'Investigating internally.',
        ]);

        $case->refresh();
        $this->assertNull($case->first_response_at);
        $this->assertSame('due', $case->first_response_sla_state);

        Carbon::setTestNow('2026-09-25 13:30:00');
        $case->refresh();
        $this->assertSame('breached', $case->first_response_sla_state);

        $this->actingAs($agent)->post(route('admin.support.reply', $case), [
            'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
            'message' => 'Customer-facing response.',
        ]);

        $case->refresh();
        $this->assertSame('breached', $case->first_response_sla_state);
    }

    public function test_support_manager_can_configure_sla_targets_and_reply_templates(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $agent = $this->staffWithRole('support_agent');

        $this->actingAs($agent)
            ->put(route('admin.support.settings.sla'), [
                'sla' => [
                    'low' => ['first_response_hours' => 30, 'resolution_hours' => 144],
                    'normal' => ['first_response_hours' => 10, 'resolution_hours' => 80],
                    'high' => ['first_response_hours' => 3, 'resolution_hours' => 30],
                    'urgent' => ['first_response_hours' => 1, 'resolution_hours' => 6],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('10', (string) WebsiteSetting::getValue('support_sla_first_response_normal_hours'));
        $this->assertSame('6', (string) WebsiteSetting::getValue('support_sla_resolution_urgent_hours'));

        $this->post(route('admin.support.templates.store'), [
            'name' => 'Order follow-up',
            'name_ar' => 'متابعة الطلب',
            'body' => 'We are checking your order.',
            'body_ar' => 'نحن نراجع طلبك.',
            'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
            'sort_order' => 10,
        ])->assertRedirect();

        $template = SupportReplyTemplate::query()->firstOrFail();

        $this->assertTrue($template->is_active);
        $this->assertSame('Order follow-up', $template->displayName());

        app()->setLocale('ar');
        $this->assertSame('متابعة الطلب', $template->displayName());
        $this->assertSame('نحن نراجع طلبك.', $template->displayBody());
    }

    public function test_active_reply_template_is_available_in_case_composer_and_cashier_cannot_manage_support_settings(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $agent = $this->staffWithRole('support_agent');
        $cashier = $this->staffWithRole('cashier');
        $customer = User::factory()->create(['role_as' => 0]);

        $template = SupportReplyTemplate::query()->create([
            'name' => 'Delivery update',
            'body' => 'Your delivery is being reviewed.',
            'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
            'sort_order' => 10,
            'is_active' => true,
            'created_by_user_id' => $agent->id,
        ]);

        $case = SupportCase::query()->create([
            'case_number' => 'CS-TEMPLATE-001',
            'customer_id' => $customer->id,
            'subject' => 'Delivery',
            'priority' => SupportCase::PRIORITY_NORMAL,
            'status' => SupportCase::STATUS_OPEN,
            'source' => 'customer_portal',
        ]);

        $this->actingAs($agent)
            ->get(route('admin.support.show', $case))
            ->assertOk()
            ->assertSee($template->name)
            ->assertSee('data-support-template', false);

        $this->actingAs($cashier)
            ->get(route('admin.support.settings'))
            ->assertForbidden();
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }
}
