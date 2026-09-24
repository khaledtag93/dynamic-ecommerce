<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Commerce\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCashierTest extends TestCase
{
    use RefreshDatabase;

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
