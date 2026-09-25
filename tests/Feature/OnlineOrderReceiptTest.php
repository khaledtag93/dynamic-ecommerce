<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineOrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_print_only_their_own_order_receipt_with_persisted_currency_and_values(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $order = $this->makeOrder($customer, 'USD');

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Receipt Product',
            'sku' => 'REC-001',
            'unit_price' => 50,
            'quantity' => 2,
            'line_total' => 100,
        ]);

        $response = $this->actingAs($customer)->get(route('orders.receipt', $order));

        $response->assertOk();
        $response->assertSee('data-order-receipt', false);
        $response->assertSee($order->order_number);
        $response->assertSee('Receipt Product');
        $response->assertSee('USD 120.00');
        $response->assertSee('USD 100.00');
        $response->assertSee('This document is an order receipt, not a tax or fiscal invoice.');
        $response->assertDontSee('EGP 120.00');

        $this->actingAs($otherCustomer)
            ->get(route('orders.receipt', $order))
            ->assertForbidden();
    }

    public function test_orders_view_admin_can_open_the_same_read_only_receipt(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $order = $this->makeOrder($customer, 'EUR');

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Admin Receipt Product',
            'sku' => 'ADMIN-REC-001',
            'unit_price' => 75,
            'quantity' => 1,
            'line_total' => 75,
        ]);

        $admin = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $admin->roles()->sync([$role->id]);

        $response = $this->actingAs($admin)->get(route('admin.orders.receipt', $order));

        $response->assertOk();
        $response->assertSee('data-order-receipt', false);
        $response->assertSee('Admin Receipt Product');
        $response->assertSee('EUR 120.00');
    }

    public function test_receipt_copy_is_available_in_arabic(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('إيصال الطلب', $translations['Order receipt'] ?? null);
        $this->assertSame('طباعة الإيصال', $translations['Print receipt'] ?? null);
        $this->assertSame(
            'هذا المستند إيصال طلب وليس فاتورة ضريبية أو فاتورة مالية رسمية.',
            $translations['This document is an order receipt, not a tax or fiscal invoice.'] ?? null
        );
    }

    private function makeOrder(User $customer, string $currency): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_STOREFRONT,
            'order_number' => 'WEB-REC-' . strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => $currency,
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 20,
            'tax_total' => 0,
            'grand_total' => 120,
            'refund_total' => 20,
            'customer_name' => 'Receipt Customer',
            'customer_email' => 'receipt@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Receipt Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ]);
    }
}
