<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\PosCashShift;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Commerce\PosService;
use App\Services\Commerce\PosCashShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCashierTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_open_manager_shift_review_but_operations_manager_can(): void
    {
        $cashier = User::factory()->create(['role_as' => 4]);
        $manager = User::factory()->create(['role_as' => 3]);

        $this->actingAs($cashier)
            ->get(route('admin.pos.shifts.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('admin.pos.shifts.index'))
            ->assertOk()
            ->assertSee(__('Cash Shift Review'));
    }

    public function test_arabic_pos_validation_does_not_leak_default_english_required_message(): void
    {
        app()->setLocale('ar');
        $admin = User::factory()->create(['role_as' => 1]);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => ''])
            ->assertSessionHasErrors(['barcode' => 'حقل الباركود مطلوب.']);
    }

    public function test_cashier_role_can_use_pos_without_broader_order_management_access(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = User::factory()->create(['role_as' => 1]);
        $cashierRole = Role::query()->where('slug', 'cashier')->firstOrFail();
        $cashier->roles()->sync([$cashierRole->id]);

        $this->actingAs($cashier)
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee(__('Point of Sale'))
            ->assertSee('id="posBarcodeInput"', false);

        $this->get(route('admin.orders.index'))->assertForbidden();
        $this->assertTrue($cashier->fresh()->hasPermission('pos.manage'));
        $this->assertFalse($cashier->fresh()->hasPermission('orders.view'));
    }

    public function test_customer_cannot_access_pos(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.pos.index'))
            ->assertRedirect('/');
    }

    public function test_scanning_simple_product_adds_one_unit_without_changing_stock(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Simple Product', '6224000000001', 3, false, 25);

        $this->actingAs($admin)->get(route('admin.pos.index'))->assertOk();
        $cart = PosCart::query()->where('cashier_user_id', $admin->id)->where('status', PosCart::STATUS_OPEN)->firstOrFail();

        $this->post(route('admin.pos.scan', $cart), [
            'barcode' => $product->barcode,
        ])->assertRedirect(route('admin.pos.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('pos_cart_items', [
            'pos_cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ]);
        $this->assertSame(3, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_pos_live_product_lookup_supports_name_and_barcode_free_products(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('Live Search Jacket', '', 4, false, 55);

        $this->actingAs($admin)
            ->getJson(route('admin.pos.lookups.products', ['q' => 'Jacket']))
            ->assertOk()
            ->assertJsonPath('results.0.product_id', $product->id)
            ->assertJsonPath('results.0.selectable', true);
    }

    public function test_pos_live_customer_lookup_supports_contains_email_search(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $customer = User::factory()->create(['role_as' => 0, 'name' => 'Lookup Customer', 'email' => 'lookup.customer@example.test']);

        $this->actingAs($admin)
            ->getJson(route('admin.pos.lookups.customers', ['q' => 'customer@example']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $customer->id)
            ->assertJsonPath('results.0.email', $customer->email);
    }

    public function test_manual_catalog_add_does_not_require_a_barcode(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('Manual POS Product', '', 3, false, 22);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.catalog.add', ['posCart' => $cart->id, 'product' => $product->id]))
            ->assertRedirect(route('admin.pos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pos_cart_items', [
            'pos_cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ]);
        $this->assertSame(3, (int) $product->fresh()->quantity);
    }

    public function test_manual_catalog_add_requires_exact_variant_for_variant_products(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('Manual Variant Product', '', 0, true, 30);
        $variant = $this->variant($product, 'MANUAL-VAR-1', '', 2, 35);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.catalog.add', ['posCart' => $cart->id, 'product' => $product->id]))
            ->assertSessionHasErrors('product');

        $this->post(route('admin.pos.catalog.add', ['posCart' => $cart->id, 'product' => $product->id]), [
            'variant_id' => $variant->id,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('pos_cart_items', [
            'pos_cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    public function test_variant_product_requires_exact_variant_barcode(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Variant Product', '6224000000002', 0, true, 30);
        $variant = $this->variant($product, 'POS-VAR-001', '6224000000021', 2, 35);

        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHasErrors('barcode');

        $this->assertDatabaseCount('pos_cart_items', 0);

        $this->post(route('admin.pos.scan', $cart), ['barcode' => $variant->barcode])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pos_cart_items', [
            'pos_cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $this->assertSame(2, (int) $variant->fresh()->stock);
    }

    public function test_scanning_cannot_put_more_units_in_cart_than_current_stock(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Limited Product', '6224000000003', 2, false, 15);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin);

        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHasErrors('barcode');

        $this->assertSame(2, (int) PosCartItem::query()->where('pos_cart_id', $cart->id)->value('quantity'));
        $this->assertSame(2, (int) $product->fresh()->quantity);
    }

    public function test_quantity_update_rejects_stale_form_and_stock_overflow(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Quantity Product', '6224000000004', 4, false, 10);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $item = PosCartItem::query()->where('pos_cart_id', $cart->id)->firstOrFail();

        $this->patch(route('admin.pos.items.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'expected_quantity' => 1,
            'quantity' => 3,
        ])->assertSessionHas('success');

        $this->patch(route('admin.pos.items.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'expected_quantity' => 1,
            'quantity' => 2,
        ])->assertSessionHasErrors('quantity');

        $this->patch(route('admin.pos.items.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'expected_quantity' => 3,
            'quantity' => 5,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(3, (int) $item->fresh()->quantity);
        $this->assertSame(4, (int) $product->fresh()->quantity);
    }

    public function test_cash_checkout_is_atomic_paid_inventory_safe_and_replay_safe(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Cash Product', '6224000000005', 5, false, 20, 8);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin);
        app(PosCashShiftService::class)->openShift($admin, 100);
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');

        $response = $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 50,
            'customer_name' => 'Counter Customer',
        ]);

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();

        $response->assertRedirect(route('admin.pos.sales.show', $order))->assertSessionHas('success');

        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertSame(Order::PAYMENT_METHOD_POS_CASH, $order->payment_method);
        $this->assertSame('40.00', $order->grand_total);
        $this->assertSame('16.00', $order->cost_total);
        $this->assertSame('24.00', $order->profit_total);
        $this->assertNull($order->user_id);
        $this->assertNull($order->customer_email);
        $this->assertNull($order->shipping_address_line_1);
        $this->assertSame(10.0, (float) data_get($order->meta, 'pos.change_due'));

        $this->assertSame(3, (int) $product->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_ORDER_OUT,
            'quantity_change' => -2,
            'balance_after' => 3,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_POS_CASH,
            'status' => Payment::STATUS_PAID,
            'amount' => 40,
        ]);
        $this->assertDatabaseHas('pos_carts', [
            'id' => $cart->id,
            'status' => PosCart::STATUS_COMPLETED,
            'order_id' => $order->id,
            'open_token' => null,
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'pos_sale_completed',
            'subject_id' => $order->id,
        ]);

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 50,
        ])->assertRedirect(route('admin.pos.sales.show', $order))->assertSessionHas('warning');

        $this->assertSame(3, (int) $product->fresh()->quantity);
        $this->assertSame(1, Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->count());
        $this->assertSame(1, InventoryMovement::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
    }

    public function test_cash_checkout_requires_an_open_cash_shift_but_card_checkout_does_not(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $cashProduct = $this->product('POS Shift Guard Cash', '6224000000090', 2, false, 20);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $cashProduct->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 20,
        ])->assertSessionHasErrors('cash_shift');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, (int) $cashProduct->fresh()->quantity);

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CARD,
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_cash_shift_reconciliation_records_expected_cash_and_variance(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Shift Product', '6224000000091', 2, false, 30);
        $shiftService = app(PosCashShiftService::class);
        $shift = $shiftService->openShift($admin, 100, 'Opening float');
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');
        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 30,
        ])->assertSessionHas('success');

        $summary = $shiftService->summary($shift->fresh());
        $this->assertSame(100.0, $summary['opening_cash']);
        $this->assertSame(30.0, $summary['cash_sales']);
        $this->assertSame(130.0, $summary['expected_cash']);

        $closed = $shiftService->closeShift($shift, $admin, 128, 'Two pounds short');
        $this->assertSame('130.00', $closed->expected_cash);
        $this->assertSame('128.00', $closed->closing_cash_counted);
        $this->assertSame('-2.00', $closed->cash_variance);
        $this->assertNotNull($closed->closed_at);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'pos_cash_shift_closed',
            'subject_id' => $shift->id,
        ]);
    }

    public function test_cash_checkout_rejects_insufficient_cash_without_writing_sale_or_stock(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Cash Guard Product', '6224000000006', 2, false, 30);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 20,
        ])->assertSessionHasErrors('cash_received');

        $this->assertSame(2, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(PosCart::STATUS_OPEN, $cart->fresh()->status);
    }

    public function test_card_terminal_checkout_does_not_require_cash_received(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Card Product', '6224000000007', 2, false, 45);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CARD,
        ])->assertSessionHas('success');

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();

        $this->assertSame(Order::PAYMENT_METHOD_POS_CARD, $order->payment_method);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertSame(0.0, (float) data_get($order->meta, 'pos.change_due'));
        $this->assertSame(1, (int) $product->fresh()->quantity);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_POS_CARD,
            'provider' => 'card_terminal',
            'status' => Payment::STATUS_PAID,
        ]);
    }

    public function test_pos_customer_search_attach_and_checkout_links_customer_account(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $customer = User::factory()->create([
            'role_as' => 0,
            'name' => 'Mona POS Customer',
            'email' => 'mona.pos@example.test',
        ]);
        $staff = User::factory()->create([
            'role_as' => 1,
            'name' => 'Mona Internal Staff',
            'email' => 'mona.staff@example.test',
        ]);
        $product = $this->product('POS Customer Product', '6224000000014', 3, false, 25, 10);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->get(route('admin.pos.index', ['customer_search' => 'Mona']))
            ->assertOk()
            ->assertSee('Mona POS Customer')
            ->assertSee('mona.pos@example.test')
            ->assertDontSee('Mona Internal Staff')
            ->assertDontSee('mona.staff@example.test');

        $this->post(route('admin.pos.customer.attach', [
            'posCart' => $cart->id,
            'user' => $customer->id,
        ]))->assertRedirect(route('admin.pos.index'))->assertSessionHas('success');

        $attachedCart = $cart->fresh('customer');
        $this->assertSame($customer->id, (int) $attachedCart->customer_user_id);
        $this->assertSame($customer->name, $attachedCart->customer_name);
        $this->assertSame($customer->email, $attachedCart->customer->email);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'pos_customer_attached',
            'subject_id' => $cart->id,
        ]);

        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CARD,
            'customer_name' => 'Ignored manual name',
        ])->assertSessionHas('success');

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();

        $this->assertSame($customer->id, (int) $order->user_id);
        $this->assertSame($customer->name, $order->customer_name);
        $this->assertSame($customer->email, $order->customer_email);
        $this->assertSame($customer->id, (int) data_get($order->meta, 'customer_user_id'));
        $this->assertSame(2, (int) $product->fresh()->quantity);

        $this->actingAs($customer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee(__('In-store purchase'))
            ->assertSee(__('This purchase was completed at the store counter. No shipping address is required.'))
            ->assertSee(__('Paid by card terminal at the store counter.'))
            ->assertDontSee(__('Shipping address'));
    }

    public function test_pos_customer_attachment_is_owner_scoped_and_rejects_staff_accounts(): void
    {
        $firstCashier = User::factory()->create(['role_as' => 1]);
        $secondCashier = User::factory()->create(['role_as' => 1]);
        $customer = User::factory()->create(['role_as' => 0]);
        $staff = User::factory()->create(['role_as' => 1]);
        $cart = app(PosService::class)->cartFor($firstCashier);

        $this->actingAs($secondCashier)
            ->post(route('admin.pos.customer.attach', [
                'posCart' => $cart->id,
                'user' => $customer->id,
            ]))
            ->assertSessionHasErrors('cart');

        $this->assertNull($cart->fresh()->customer_user_id);

        $this->actingAs($firstCashier)
            ->post(route('admin.pos.customer.attach', [
                'posCart' => $cart->id,
                'user' => $staff->id,
            ]))
            ->assertSessionHasErrors('customer');

        $this->assertNull($cart->fresh()->customer_user_id);

        $this->post(route('admin.pos.customer.attach', [
            'posCart' => $cart->id,
            'user' => $customer->id,
        ]))->assertSessionHas('success');

        $this->delete(route('admin.pos.customer.detach', $cart))
            ->assertRedirect(route('admin.pos.index'))
            ->assertSessionHas('success');

        $this->assertNull($cart->fresh()->customer_user_id);
        $this->assertNull($cart->fresh()->customer_name);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $firstCashier->id,
            'action' => 'pos_customer_detached',
            'subject_id' => $cart->id,
        ]);
    }

    public function test_pos_checkout_rechecks_attached_customer_account_before_writing_sale(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $customer = User::factory()->create([
            'role_as' => 0,
            'name' => 'Customer Before Role Change',
            'email' => 'before-change@example.test',
        ]);
        $product = $this->product('POS Customer Recheck Product', '6224000000015', 2, false, 30);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.customer.attach', [
                'posCart' => $cart->id,
                'user' => $customer->id,
            ]))
            ->assertSessionHas('success');

        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $customer->update(['role_as' => 1]);

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CARD,
        ])->assertSessionHasErrors('customer');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(2, (int) $product->fresh()->quantity);
        $this->assertSame(PosCart::STATUS_OPEN, $cart->fresh()->status);
    }

    public function test_cashier_can_hold_and_resume_sale_without_mutating_inventory(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Held Product', '6224000000010', 5, false, 22, 8);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin);
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');

        $this->post(route('admin.pos.hold', $cart), [
            'hold_label' => 'Counter A',
            'customer_name' => 'Held Customer',
            'notes' => 'Customer will return.',
        ])->assertRedirect(route('admin.pos.index'))->assertSessionHas('success');

        $heldCart = $cart->fresh();
        $this->assertSame(PosCart::STATUS_HELD, $heldCart->status);
        $this->assertNull($heldCart->open_token);
        $this->assertSame('Counter A', $heldCart->hold_label);
        $this->assertNotNull($heldCart->held_at);
        $this->assertSame('Held Customer', $heldCart->customer_name);
        $this->assertSame('Customer will return.', $heldCart->notes);
        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'pos_sale_held',
            'subject_id' => $heldCart->id,
        ]);

        $this->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('Counter A')
            ->assertSee(__('Resume sale'));

        $newOpenCart = PosCart::query()
            ->where('cashier_user_id', $admin->id)
            ->where('status', PosCart::STATUS_OPEN)
            ->firstOrFail();

        $this->assertNotSame($heldCart->id, $newOpenCart->id);
        $this->assertTrue($newOpenCart->items()->doesntExist());

        $this->post(route('admin.pos.resume', $heldCart))
            ->assertRedirect(route('admin.pos.index'))
            ->assertSessionHas('success');

        $this->assertSame(PosCart::STATUS_ABANDONED, $newOpenCart->fresh()->status);
        $this->assertNull($newOpenCart->fresh()->open_token);

        $resumedCart = $heldCart->fresh();
        $this->assertSame(PosCart::STATUS_OPEN, $resumedCart->status);
        $this->assertSame('cashier:' . $admin->id, $resumedCart->open_token);
        $this->assertNull($resumedCart->held_at);
        $this->assertSame('Held Customer', $resumedCart->customer_name);
        $this->assertSame('Customer will return.', $resumedCart->notes);
        $this->assertSame(2, (int) $resumedCart->items()->sum('quantity'));
        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'pos_sale_resumed',
            'subject_id' => $resumedCart->id,
        ]);
    }

    public function test_resume_is_blocked_when_current_sale_contains_items(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $heldProduct = $this->product('POS First Held Product', '6224000000011', 3, false, 12);
        $currentProduct = $this->product('POS Current Product', '6224000000012', 3, false, 14);

        $heldCart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $heldCart), ['barcode' => $heldProduct->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.hold', $heldCart), [
            'hold_label' => 'Waiting customer',
        ])->assertSessionHas('success');

        $currentCart = app(PosService::class)->cartFor($admin);

        $this->post(route('admin.pos.scan', $currentCart), ['barcode' => $currentProduct->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.resume', $heldCart))
            ->assertSessionHasErrors('cart');

        $this->assertSame(PosCart::STATUS_HELD, $heldCart->fresh()->status);
        $this->assertSame(PosCart::STATUS_OPEN, $currentCart->fresh()->status);
        $this->assertSame('cashier:' . $admin->id, $currentCart->fresh()->open_token);
        $this->assertSame(1, (int) $currentCart->items()->sum('quantity'));
    }

    public function test_held_sale_discard_and_ownership_guards_do_not_touch_stock(): void
    {
        $firstCashier = User::factory()->create(['role_as' => 1]);
        $secondCashier = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Discard Held Product', '6224000000013', 4, false, 16);
        $cart = app(PosService::class)->cartFor($firstCashier);

        $this->actingAs($firstCashier)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.hold', $cart), [
            'hold_label' => 'Do not lose',
        ])->assertSessionHas('success');

        $this->actingAs($secondCashier)
            ->post(route('admin.pos.resume', $cart))
            ->assertSessionHasErrors('cart');

        $this->delete(route('admin.pos.held.destroy', $cart))
            ->assertSessionHasErrors('cart');

        $this->assertSame(PosCart::STATUS_HELD, $cart->fresh()->status);
        $this->assertSame(4, (int) $product->fresh()->quantity);

        $this->actingAs($firstCashier)
            ->delete(route('admin.pos.held.destroy', $cart))
            ->assertRedirect(route('admin.pos.index'))
            ->assertSessionHas('success');

        $this->assertSame(PosCart::STATUS_ABANDONED, $cart->fresh()->status);
        $this->assertNull($cart->fresh()->held_at);
        $this->assertSame(4, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $firstCashier->id,
            'action' => 'pos_held_sale_discarded',
            'subject_id' => $cart->id,
        ]);
    }

    public function test_cashier_role_cannot_apply_pos_discounts(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = User::factory()->create(['role_as' => 1]);
        $cashierRole = Role::query()->where('slug', 'cashier')->firstOrFail();
        $cashier->roles()->sync([$cashierRole->id]);

        $product = $this->product('POS Discount Permission Product', '6224000000010', 2, false, 25);
        $cart = app(PosService::class)->cartFor($cashier);

        $this->actingAs($cashier)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $item = PosCartItem::query()->where('pos_cart_id', $cart->id)->firstOrFail();

        $this->patch(route('admin.pos.items.discount.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'discount_type' => PosService::DISCOUNT_TYPE_PERCENT,
            'discount_value' => 10,
            'discount_reason' => 'Not authorized',
        ])->assertForbidden();

        $this->patch(route('admin.pos.discount.update', $cart), [
            'discount_type' => PosService::DISCOUNT_TYPE_FIXED,
            'discount_value' => 5,
            'discount_reason' => 'Not authorized',
        ])->assertForbidden();

        $this->assertFalse($cashier->fresh()->hasPermission('pos.discount'));
        $this->assertNull($item->fresh()->discount_type);
        $this->assertNull($cart->fresh()->discount_type);
    }

    public function test_pos_line_and_sale_discounts_flow_into_order_payment_profit_and_change(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Discount Product', '6224000000011', 5, false, 100, 40);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin);
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');

        $item = PosCartItem::query()->where('pos_cart_id', $cart->id)->firstOrFail();

        $this->patch(route('admin.pos.items.discount.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'discount_type' => PosService::DISCOUNT_TYPE_PERCENT,
            'discount_value' => 10,
            'discount_reason' => 'Loyalty adjustment',
        ])->assertSessionHas('success');

        $this->patch(route('admin.pos.discount.update', $cart), [
            'discount_type' => PosService::DISCOUNT_TYPE_FIXED,
            'discount_value' => 30,
            'discount_reason' => 'Manager approved promotion',
        ])->assertSessionHas('success');

        $summary = app(PosService::class)->summary($cart->fresh(['items.product', 'items.variant']));
        $this->assertSame(200.0, $summary['subtotal']);
        $this->assertSame(20.0, $summary['line_discount_total']);
        $this->assertSame(30.0, $summary['order_discount_total']);
        $this->assertSame(50.0, $summary['discount_total']);
        $this->assertSame(150.0, $summary['grand_total']);

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 160,
        ])->assertSessionHas('success');

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();
        $orderItem = $order->items()->firstOrFail();

        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('50.00', $order->discount_total);
        $this->assertSame('150.00', $order->grand_total);
        $this->assertSame('80.00', $order->cost_total);
        $this->assertSame('70.00', $order->profit_total);
        $this->assertSame('150.00', $orderItem->line_total);
        $this->assertSame('70.00', $orderItem->profit_amount);
        $this->assertSame(20.0, (float) data_get($orderItem->meta, 'pos.discount.line_amount'));
        $this->assertSame(30.0, (float) data_get($orderItem->meta, 'pos.discount.order_share'));
        $this->assertSame(50.0, (float) data_get($orderItem->meta, 'pos.discount.total_amount'));
        $this->assertSame('Manager approved promotion', data_get($order->meta, 'pos.discounts.order_discount.reason'));
        $this->assertSame(10.0, (float) data_get($order->meta, 'pos.change_due'));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 150,
            'status' => Payment::STATUS_PAID,
        ]);
        $this->assertSame(3, (int) $product->fresh()->quantity);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'pos_line_discount_updated',
            'subject_id' => $cart->id,
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'pos_sale_discount_updated',
            'subject_id' => $cart->id,
        ]);
    }

    public function test_pos_discount_requires_reason_and_cannot_exceed_eligible_total(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Discount Guard Product', '6224000000012', 2, false, 20);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $item = PosCartItem::query()->where('pos_cart_id', $cart->id)->firstOrFail();

        $this->patch(route('admin.pos.items.discount.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'discount_type' => PosService::DISCOUNT_TYPE_FIXED,
            'discount_value' => 5,
            'discount_reason' => '',
        ])->assertSessionHasErrors('discount_reason');

        $this->patch(route('admin.pos.items.discount.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'discount_type' => PosService::DISCOUNT_TYPE_FIXED,
            'discount_value' => 25,
            'discount_reason' => 'Too large',
        ])->assertSessionHasErrors('discount_value');

        $this->patch(route('admin.pos.discount.update', $cart), [
            'discount_type' => PosService::DISCOUNT_TYPE_PERCENT,
            'discount_value' => 101,
            'discount_reason' => 'Too large',
        ])->assertSessionHasErrors('discount_value');

        $this->assertNull($item->fresh()->discount_type);
        $this->assertNull($cart->fresh()->discount_type);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, (int) $product->fresh()->quantity);
    }

    public function test_pos_receipt_is_read_only_and_supports_print_paper_sizes(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Receipt Product', '6224000000009', 3, false, 18, 7);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 20,
            'customer_name' => 'Receipt Customer',
        ])->assertSessionHas('success');

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();
        $orderCount = Order::query()->count();
        $paymentCount = Payment::query()->count();
        $movementCount = InventoryMovement::query()->count();
        $stockAfterSale = (int) $product->fresh()->quantity;

        $this->get(route('admin.pos.sales.show', $order) . '?receipt=1&paper=58')
            ->assertOk()
            ->assertSee(__('Sales receipt'))
            ->assertSee($order->order_number)
            ->assertSee('POS Receipt Product')
            ->assertSee('data-receipt-paper="58"', false);

        $this->get(route('admin.pos.sales.show', $order) . '?receipt=1&paper=unsupported')
            ->assertOk()
            ->assertSee('data-receipt-paper="80"', false);

        $this->assertSame($orderCount, Order::query()->count());
        $this->assertSame($paymentCount, Payment::query()->count());
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame($stockAfterSale, (int) $product->fresh()->quantity);
    }

    public function test_completed_sale_page_is_limited_to_owner_or_broader_order_reviewers(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $firstCashier = User::factory()->create(['role_as' => 1]);
        $secondCashier = User::factory()->create(['role_as' => 1]);
        $cashierRole = Role::query()->where('slug', 'cashier')->firstOrFail();
        $firstCashier->roles()->sync([$cashierRole->id]);
        $secondCashier->roles()->sync([$cashierRole->id]);

        $product = $this->product('POS Ownership Product', '6224000000008', 2, false, 12);
        $cart = app(PosService::class)->cartFor($firstCashier);

        $this->actingAs($firstCashier)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CARD,
        ])->assertSessionHas('success');

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();

        $this->get(route('admin.pos.sales.show', $order))->assertOk();

        $this->actingAs($secondCashier)
            ->get(route('admin.pos.sales.show', $order))
            ->assertForbidden();

        $this->get(route('admin.pos.sales.show', $order) . '?receipt=1&paper=80')
            ->assertForbidden();
    }

    public function test_pos_item_return_uses_discounted_snapshot_and_restocks_once(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('POS Return Product', '6224000000020', 5, false, 100, 40);
        $cart = app(PosService::class)->cartFor($admin);

        $this->actingAs($admin);
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');
        $this->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])->assertSessionHas('success');

        $item = PosCartItem::query()->where('pos_cart_id', $cart->id)->firstOrFail();
        $this->patch(route('admin.pos.items.discount.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]), [
            'discount_type' => PosService::DISCOUNT_TYPE_PERCENT,
            'discount_value' => 10,
            'discount_reason' => 'Return snapshot test',
        ])->assertSessionHas('success');
        $this->patch(route('admin.pos.discount.update', $cart), [
            'discount_type' => PosService::DISCOUNT_TYPE_FIXED,
            'discount_value' => 30,
            'discount_reason' => 'Manager discount',
        ])->assertSessionHas('success');

        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
            'cash_received' => 160,
        ])->assertSessionHas('success');

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();
        $orderItem = $order->items()->firstOrFail();
        $this->assertSame('150.00', $orderItem->line_total);
        $this->assertSame(3, (int) $product->fresh()->quantity);

        $this->post(route('admin.pos.sales.return', $order), [
            'items' => [$orderItem->id => 1],
            'reason' => 'Customer changed mind',
            'notes' => 'Returned at counter',
        ])->assertRedirect(route('admin.pos.sales.show', $order))->assertSessionHas('success');

        $this->assertDatabaseHas('order_refunds', [
            'order_id' => $order->id,
            'amount' => 75,
            'reason' => 'Customer changed mind',
            'processed_by' => $admin->id,
        ]);
        $refundId = $order->refunds()->value('id');
        $this->assertDatabaseHas('pos_return_items', [
            'order_refund_id' => $refundId,
            'order_item_id' => $orderItem->id,
            'quantity' => 1,
            'amount' => 75,
            'restocked' => 1,
        ]);
        $this->assertSame(4, (int) $product->fresh()->quantity);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame('75.00', $order->fresh()->refund_total);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_REFUND_RESTOCK,
            'quantity' => 1,
        ]);

        $this->post(route('admin.pos.sales.return', $order), [
            'items' => [$orderItem->id => 2],
            'reason' => 'Attempt duplicate return',
        ])->assertSessionHasErrors('items');

        $this->assertSame(4, (int) $product->fresh()->quantity);
        $this->assertSame(1, (int) $order->refunds()->count());
    }

    public function test_cashier_role_cannot_process_pos_returns_without_return_permission(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = User::factory()->create(['role_as' => 1]);
        $cashierRole = Role::query()->where('slug', 'cashier')->firstOrFail();
        $cashier->roles()->sync([$cashierRole->id]);
        $product = $this->product('POS Return Permission Product', '6224000000021', 2, false, 25);
        $cart = app(PosService::class)->cartFor($cashier);

        $this->actingAs($cashier)
            ->post(route('admin.pos.scan', $cart), ['barcode' => $product->barcode])
            ->assertSessionHas('success');
        $this->post(route('admin.pos.checkout', $cart), [
            'payment_method' => Order::PAYMENT_METHOD_POS_CARD,
        ])->assertSessionHas('success');

        $order = Order::query()->where('sales_channel', Order::SALES_CHANNEL_POS)->firstOrFail();
        $orderItem = $order->items()->firstOrFail();

        $this->post(route('admin.pos.sales.return', $order), [
            'items' => [$orderItem->id => 1],
            'reason' => 'Not authorized',
        ])->assertForbidden();

        $this->assertFalse($cashier->fresh()->hasPermission('pos.return'));
        $this->assertDatabaseCount('order_refunds', 0);
        $this->assertDatabaseCount('pos_return_items', 0);
        $this->assertSame(1, (int) $product->fresh()->quantity);
    }

    private function product(
        string $name,
        string $barcode,
        int $quantity,
        bool $hasVariants,
        float $price,
        float $cost = 5
    ): Product {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'POS Category ' . $token,
            'slug' => 'pos-category-' . $token,
            'description' => 'POS test category',
            'meta_title' => 'POS test',
            'meta_keyword' => 'pos',
            'meta_description' => 'POS test category',
            'status' => false,
        ]);

        return Product::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $token,
            'sku' => 'POS-' . Str::upper(Str::random(7)),
            'barcode' => $barcode,
            'category_id' => $category->id,
            'base_price' => $price,
            'cost_price' => $cost,
            'quantity' => $quantity,
            'has_variants' => $hasVariants,
            'stock_status' => $quantity > 0 ? 'in_stock' : 'out_of_stock',
            'status' => true,
        ]);
    }

    private function variant(
        Product $product,
        string $sku,
        string $barcode,
        int $stock,
        float $price
    ): ProductVariant {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'barcode' => $barcode,
            'price' => $price,
            'cost_price' => 9,
            'stock' => $stock,
            'is_default' => $product->variants()->doesntExist(),
            'status' => true,
        ]);
    }
}
