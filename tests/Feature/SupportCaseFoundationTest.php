<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\SupportCase;
use App\Models\SupportCaseMessage;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportCaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_roles_are_explicit_and_cashier_is_not_granted_support_access(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $supportAgent = $this->staffWithRole('support_agent');
        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');

        $this->assertTrue($supportAgent->hasPermission('support.view'));
        $this->assertTrue($supportAgent->hasPermission('support.manage'));
        $this->assertTrue($manager->hasPermission('support.view'));
        $this->assertTrue($manager->hasPermission('support.manage'));
        $this->assertFalse($cashier->hasPermission('support.view'));
        $this->assertFalse($cashier->hasPermission('support.manage'));

        $this->actingAs($cashier)
            ->get(route('admin.support.index'))
            ->assertForbidden();
    }

    public function test_admin_create_lists_normal_customers_and_assignment_rejects_staff_without_support_access(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $supportAgent = $this->staffWithRole('support_agent');
        $cashier = $this->staffWithRole('cashier');
        $customer = User::factory()->create();

        $this->actingAs($supportAgent)
            ->get(route('admin.support.create'))
            ->assertOk()
            ->assertSee($customer->email);

        $case = SupportCase::query()->create([
            'case_number' => 'CS-ASSIGN-001',
            'customer_id' => $customer->id,
            'subject' => 'Assignment guard',
            'priority' => SupportCase::PRIORITY_NORMAL,
            'status' => SupportCase::STATUS_OPEN,
            'source' => 'admin',
        ]);

        $this->patch(route('admin.support.update', $case), [
            'status' => SupportCase::STATUS_OPEN,
            'priority' => SupportCase::PRIORITY_NORMAL,
            'assigned_to_user_id' => $cashier->id,
        ])->assertSessionHasErrors('assigned_to_user_id');

        $this->assertNull($case->fresh()->assigned_to_user_id);
    }

    public function test_customer_can_create_case_only_for_an_order_owned_by_their_account(): void
    {
        $customer = User::factory()->create(['role_as' => 0]);
        $otherCustomer = User::factory()->create(['role_as' => 0]);
        $ownOrder = $this->orderFor($customer, 'SUP-OWN-001');
        $otherOrder = $this->orderFor($otherCustomer, 'SUP-OTHER-001');

        $this->actingAs($customer)
            ->post(route('support.store'), [
                'order_id' => $ownOrder->id,
                'subject' => 'Delivery question',
                'category' => 'delivery',
                'message' => 'Please confirm the delivery timing.',
            ])
            ->assertRedirect();

        $case = SupportCase::query()->firstOrFail();

        $this->assertSame($customer->id, $case->customer_id);
        $this->assertSame($ownOrder->id, $case->order_id);
        $this->assertSame(SupportCase::STATUS_OPEN, $case->status);
        $this->assertSame(SupportCase::PRIORITY_NORMAL, $case->priority);
        $this->assertNotNull($case->last_customer_message_at);

        $this->assertDatabaseHas('support_case_messages', [
            'support_case_id' => $case->id,
            'author_user_id' => $customer->id,
            'author_type' => SupportCaseMessage::AUTHOR_CUSTOMER,
            'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
            'body' => 'Please confirm the delivery timing.',
        ]);

        $this->post(route('support.store'), [
            'order_id' => $otherOrder->id,
            'subject' => 'Wrong order attempt',
            'message' => 'This order is not mine.',
        ])->assertSessionHasErrors('order_id');

        $this->assertDatabaseCount('support_cases', 1);
    }

    public function test_customer_case_visibility_hides_internal_notes_and_blocks_other_customers(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create(['role_as' => 0]);
        $otherCustomer = User::factory()->create(['role_as' => 0]);
        $supportAgent = $this->staffWithRole('support_agent');

        $this->actingAs($customer)
            ->post(route('support.store'), [
                'subject' => 'Payment follow-up',
                'message' => 'I need help with a payment.',
            ])
            ->assertRedirect();

        $case = SupportCase::query()->firstOrFail();

        $this->actingAs($supportAgent)
            ->post(route('admin.support.reply', $case), [
                'visibility' => SupportCaseMessage::VISIBILITY_INTERNAL,
                'message' => 'Internal investigation note.',
            ])
            ->assertRedirect();

        $this->assertNull($case->fresh()->first_response_at);

        $this->post(route('admin.support.reply', $case), [
            'visibility' => SupportCaseMessage::VISIBILITY_CUSTOMER,
            'message' => 'We are checking this for you.',
        ])->assertRedirect();

        $case->refresh();
        $this->assertNotNull($case->first_response_at);
        $this->assertSame(SupportCase::STATUS_PENDING_CUSTOMER, $case->status);

        $this->actingAs($customer)
            ->get(route('support.show', $case))
            ->assertOk()
            ->assertSee('We are checking this for you.')
            ->assertDontSee('Internal investigation note.');

        $this->actingAs($otherCustomer)
            ->get(route('support.show', $case))
            ->assertNotFound();
    }

    public function test_customer_reply_reopens_resolved_case_but_closed_case_is_terminal(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create(['role_as' => 0]);
        $supportAgent = $this->staffWithRole('support_agent');

        $this->actingAs($customer)
            ->post(route('support.store'), [
                'subject' => 'Return question',
                'message' => 'Please help with my return.',
            ])
            ->assertRedirect();

        $case = SupportCase::query()->firstOrFail();

        $this->actingAs($supportAgent)
            ->patch(route('admin.support.update', $case), [
                'status' => SupportCase::STATUS_RESOLVED,
                'priority' => SupportCase::PRIORITY_NORMAL,
                'assigned_to_user_id' => $supportAgent->id,
            ])
            ->assertRedirect();

        $this->assertNotNull($case->fresh()->resolved_at);

        $this->actingAs($customer)
            ->post(route('support.reply', $case), [
                'message' => 'The issue is still happening.',
            ])
            ->assertRedirect();

        $case->refresh();
        $this->assertSame(SupportCase::STATUS_OPEN, $case->status);
        $this->assertNull($case->resolved_at);

        $this->actingAs($supportAgent)
            ->patch(route('admin.support.update', $case), [
                'status' => SupportCase::STATUS_CLOSED,
                'priority' => SupportCase::PRIORITY_NORMAL,
                'assigned_to_user_id' => $supportAgent->id,
            ])
            ->assertRedirect();

        $this->actingAs($customer)
            ->post(route('support.reply', $case), [
                'message' => 'Attempt after close.',
            ])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseMissing('support_case_messages', [
            'support_case_id' => $case->id,
            'body' => 'Attempt after close.',
        ]);
    }

    public function test_staff_case_creation_rejects_staff_customer_links_and_audits_workflow_changes(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $supportAgent = $this->staffWithRole('support_agent');
        $customer = User::factory()->create(['role_as' => 0]);
        $order = $this->orderFor($customer, 'SUP-ADMIN-001');

        $this->actingAs($supportAgent)
            ->post(route('admin.support.store'), [
                'customer_id' => $supportAgent->id,
                'subject' => 'Invalid staff customer',
                'priority' => SupportCase::PRIORITY_HIGH,
                'visibility' => SupportCaseMessage::VISIBILITY_INTERNAL,
                'message' => 'Should not be created.',
            ])
            ->assertSessionHasErrors('customer_id');

        $this->post(route('admin.support.store'), [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'subject' => 'Admin-created case',
            'category' => 'orders',
            'priority' => SupportCase::PRIORITY_HIGH,
            'visibility' => SupportCaseMessage::VISIBILITY_INTERNAL,
            'message' => 'Investigate this order before replying.',
        ])->assertRedirect();

        $case = SupportCase::query()->firstOrFail();

        $this->assertSame($supportAgent->id, $case->assigned_to_user_id);
        $this->assertSame(SupportCase::PRIORITY_HIGH, $case->priority);
        $this->assertNull($case->first_response_at);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $supportAgent->id,
            'action' => 'support_case_created',
            'subject_id' => $case->id,
        ]);

        $this->patch(route('admin.support.update', $case), [
            'status' => SupportCase::STATUS_PENDING_TEAM,
            'priority' => SupportCase::PRIORITY_URGENT,
            'assigned_to_user_id' => $supportAgent->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $supportAgent->id,
            'action' => 'support_case_updated',
            'subject_id' => $case->id,
        ]);
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function orderFor(User $customer, string $orderNumber): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => $orderNumber,
            'grand_total' => 100,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
        ]);
    }
}
