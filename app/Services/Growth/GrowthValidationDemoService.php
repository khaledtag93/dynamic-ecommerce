<?php

namespace App\Services\Growth;

use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\GrowthAttributionTouch;
use App\Models\GrowthCustomerScore;
use App\Models\GrowthDelivery;
use App\Models\GrowthMessageLog;
use App\Models\GrowthTriggerLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserBehavior;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GrowthValidationDemoService
{
    public const DEMO_KEY = 'growth_validation_demo';

    public function seed(bool $resetGrowthArtifacts = true): array
    {
        return DB::transaction(function () use ($resetGrowthArtifacts) {
            $category = $this->ensureDemoCategory();
            $products = $this->ensureDemoProducts($category);

            $profiles = $this->profiles();
            $summary = [
                'users' => 0,
                'orders' => 0,
                'events' => 0,
                'products' => count($products),
                'growth_artifacts_reset' => $resetGrowthArtifacts,
            ];

            foreach ($profiles as $profile) {
                $user = $this->upsertUser($profile);
                $summary['users']++;

                $this->clearUserDemoData($user, $resetGrowthArtifacts);
                $summary['orders'] += $this->seedOrdersForProfile($user, $profile, $products);
                $summary['events'] += $this->seedEventsForProfile($user, $profile, $products);
            }

            return $summary;
        });
    }

    public function clear(bool $removeProducts = false): array
    {
        return DB::transaction(function () use ($removeProducts) {
            $users = User::query()->where('email', 'like', '%+'.self::DEMO_KEY.'@%')->get();
            $summary = [
                'users' => $users->count(),
                'orders' => 0,
                'events' => 0,
                'products_removed' => 0,
            ];

            foreach ($users as $user) {
                $summary['orders'] += Order::query()->where('user_id', $user->id)->count();
                $summary['events'] += AnalyticsEvent::query()->where('user_id', $user->id)->count();
                $this->clearUserDemoData($user, true);
                $user->delete();
            }

            if ($removeProducts) {
                $productIds = Product::query()->where('slug', 'like', self::DEMO_KEY.'-%')->pluck('id');
                if ($productIds->isNotEmpty()) {
                    OrderItem::query()->whereIn('product_id', $productIds)->delete();
                    Product::query()->whereIn('id', $productIds)->delete();
                    Category::query()->where('slug', self::DEMO_KEY.'-category')->delete();
                }
                $summary['products_removed'] = $productIds->count();
            }

            return $summary;
        });
    }

    protected function clearUserDemoData(User $user, bool $resetGrowthArtifacts = true): void
    {
        AnalyticsEvent::query()->where('user_id', $user->id)->delete();
        UserBehavior::query()->where('user_id', $user->id)->delete();

        $orderIds = Order::query()->where('user_id', $user->id)->pluck('id');
        if ($orderIds->isNotEmpty()) {
            OrderItem::query()->whereIn('order_id', $orderIds)->delete();
            GrowthAttributionTouch::query()->whereIn('order_id', $orderIds)->delete();
        }

        Order::query()->where('user_id', $user->id)->delete();

        if ($resetGrowthArtifacts) {
            GrowthTriggerLog::query()->where('user_id', $user->id)->delete();
            GrowthMessageLog::query()->where('user_id', $user->id)->delete();
            GrowthDelivery::query()->where('user_id', $user->id)->delete();
            GrowthCustomerScore::query()->where('user_id', $user->id)->delete();
        }

        Coupon::query()->where('code', 'like', 'GDEMO-%')->delete();
    }

    protected function upsertUser(array $profile): User
    {
        return User::query()->updateOrCreate(
            ['email' => $profile['email']],
            [
                'name' => $profile['name'],
                'password' => Hash::make('password'),
                'role_as' => 0,
                'email_verified_at' => now(),
            ]
        );
    }

    protected function ensureDemoCategory(): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => self::DEMO_KEY.'-category'],
            [
                'name' => 'Growth Demo',
                'description' => 'Growth validation demo category',
                'meta_title' => 'Growth Demo',
                'meta_keyword' => 'growth,demo',
                'meta_description' => 'Growth validation demo category',
                'status' => 0,
            ]
        );
    }

    /**
     * @return array<int, Product>
     */
    protected function ensureDemoProducts(Category $category): array
    {
        $rows = [
            ['slug' => self::DEMO_KEY.'-stand-mixer', 'name' => 'Stand Mixer', 'base_price' => 1599, 'sale_price' => 1499, 'cost_price' => 1100, 'quantity' => 20],
            ['slug' => self::DEMO_KEY.'-cake-turntable', 'name' => 'Cake Turntable', 'base_price' => 349, 'sale_price' => 299, 'cost_price' => 180, 'quantity' => 50],
            ['slug' => self::DEMO_KEY.'-piping-set', 'name' => 'Piping Set', 'base_price' => 199, 'sale_price' => 169, 'cost_price' => 90, 'quantity' => 70],
        ];

        $products = [];

        foreach ($rows as $row) {
            $products[] = Product::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'description' => $row['name'].' validation demo product',
                    'category_id' => $category->id,
                    'base_price' => $row['base_price'],
                    'sale_price' => $row['sale_price'],
                    'cost_price' => $row['cost_price'],
                    'quantity' => $row['quantity'],
                    'status' => 1,
                    'is_featured' => true,
                    'has_variants' => false,
                    'meta_title' => $row['name'],
                    'meta_description' => $row['name'].' growth validation product',
                ]
            );
        }

        return $products;
    }

    protected function seedOrdersForProfile(User $user, array $profile, array $products): int
    {
        $count = 0;

        foreach ($profile['orders'] as $index => $order) {
            $product = $products[$order['product_index'] ?? 0] ?? $products[0];
            $quantity = (int) ($order['quantity'] ?? 1);
            $unitPrice = (float) ($order['unit_price'] ?? ($product->current_price ?: $product->base_price));
            $subtotal = round($unitPrice * $quantity, 2);
            $discount = (float) ($order['discount_total'] ?? 0);
            $shipping = (float) ($order['shipping_total'] ?? 0);
            $tax = (float) ($order['tax_total'] ?? 0);
            $grandTotal = round($subtotal - $discount + $shipping + $tax, 2);
            $costTotal = round(((float) ($product->cost_price ?? 0)) * $quantity, 2);
            $profitTotal = round($grandTotal - $costTotal, 2);
            $placedAt = Carbon::now()->subDays((int) $order['days_ago'])->setTime(12, 0)->addMinutes($index * 7);

            $createdOrder = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => 'GDEMO-'.Str::upper(Str::random(10)),
                'status' => $order['status'] ?? Order::STATUS_COMPLETED,
                'payment_status' => $order['payment_status'] ?? Order::PAYMENT_STATUS_PAID,
                'payment_method' => $order['payment_method'] ?? Order::PAYMENT_METHOD_COD,
                'delivery_status' => $order['delivery_status'] ?? Order::DELIVERY_STATUS_DELIVERED,
                'delivery_method' => $order['delivery_method'] ?? Order::DELIVERY_METHOD_STANDARD,
                'currency' => 'EGP',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shipping,
                'tax_total' => $tax,
                'grand_total' => $grandTotal,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $profile['phone'],
                'shipping_address_line_1' => $profile['address'],
                'shipping_city' => $profile['city'],
                'shipping_state' => $profile['city'],
                'shipping_country' => 'Egypt',
                'billing_same_as_shipping' => true,
                'coupon_code' => $order['coupon_code'] ?? null,
                'coupon_snapshot' => ! empty($order['coupon_code']) ? ['code' => $order['coupon_code'], 'source' => self::DEMO_KEY] : null,
                'cost_total' => $costTotal,
                'profit_total' => $profitTotal,
                'meta' => [
                    'seed_source' => self::DEMO_KEY,
                    'profile_key' => $profile['key'],
                ],
                'placed_at' => $placedAt,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            OrderItem::query()->create([
                'order_id' => $createdOrder->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $unitPrice,
                'unit_cost' => (float) ($product->cost_price ?? 0),
                'quantity' => $quantity,
                'line_total' => $subtotal,
                'profit_amount' => $profitTotal,
                'meta' => [
                    'seed_source' => self::DEMO_KEY,
                ],
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            $count++;
        }

        return $count;
    }

    protected function seedEventsForProfile(User $user, array $profile, array $products): int
    {
        $count = 0;

        foreach ($profile['events'] as $event) {
            $product = $products[$event['product_index'] ?? 0] ?? $products[0];
            $occurredAt = $event['occurred_at'] instanceof Carbon
                ? $event['occurred_at']
                : Carbon::parse($event['occurred_at']);

            AnalyticsEvent::query()->create([
                'user_id' => $user->id,
                'session_id' => $profile['session_id'],
                'event_type' => $event['event_type'],
                'entity_type' => $event['entity_type'] ?? AnalyticsEvent::ENTITY_PRODUCT,
                'entity_id' => (string) ($event['entity_id'] ?? $product->id),
                'occurred_at' => $occurredAt,
                'meta' => [
                    'seed_source' => self::DEMO_KEY,
                    'profile_key' => $profile['key'],
                    'product_slug' => $product->slug,
                ],
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            $count++;
        }

        return $count;
    }

    protected function profiles(): array
    {
        $now = now();

        return [
            [
                'key' => 'cart',
                'name' => 'Growth Demo Cart',
                'email' => 'growth-demo-cart+'.self::DEMO_KEY.'@example.com',
                'phone' => '01000000001',
                'city' => 'Cairo',
                'address' => '12 Demo Street',
                'session_id' => self::DEMO_KEY.'-cart-session',
                'orders' => [],
                'events' => [
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subHours(5), 'product_index' => 0],
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subHours(4)->subMinutes(20), 'product_index' => 0],
                    ['event_type' => AnalyticsEvent::EVENT_ADD_TO_CART, 'entity_type' => AnalyticsEvent::ENTITY_CART, 'occurred_at' => $now->copy()->subHours(2), 'product_index' => 0],
                ],
            ],
            [
                'key' => 'checkout',
                'name' => 'Growth Demo Checkout',
                'email' => 'growth-demo-checkout+'.self::DEMO_KEY.'@example.com',
                'phone' => '01000000002',
                'city' => 'Giza',
                'address' => '24 Demo Avenue',
                'session_id' => self::DEMO_KEY.'-checkout-session',
                'orders' => [],
                'events' => [
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subHours(6), 'product_index' => 1],
                    ['event_type' => AnalyticsEvent::EVENT_ADD_TO_CART, 'entity_type' => AnalyticsEvent::ENTITY_CART, 'occurred_at' => $now->copy()->subHours(3), 'product_index' => 1],
                    ['event_type' => AnalyticsEvent::EVENT_CHECKOUT_START, 'entity_type' => AnalyticsEvent::ENTITY_CHECKOUT, 'occurred_at' => $now->copy()->subHours(2), 'product_index' => 1],
                ],
            ],
            [
                'key' => 'intent',
                'name' => 'Growth Demo Intent',
                'email' => 'growth-demo-intent+'.self::DEMO_KEY.'@example.com',
                'phone' => '01000000003',
                'city' => 'Alexandria',
                'address' => '8 Demo Corniche',
                'session_id' => self::DEMO_KEY.'-intent-session',
                'orders' => [],
                'events' => [
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subHours(18), 'product_index' => 2],
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subHours(12), 'product_index' => 2],
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subHours(8), 'product_index' => 2],
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subHours(3), 'product_index' => 2],
                ],
            ],
            [
                'key' => 'repeat',
                'name' => 'Growth Demo Repeat',
                'email' => 'growth-demo-repeat+'.self::DEMO_KEY.'@example.com',
                'phone' => '01000000004',
                'city' => 'Mansoura',
                'address' => '14 Demo District',
                'session_id' => self::DEMO_KEY.'-repeat-session',
                'orders' => [
                    ['days_ago' => 50, 'product_index' => 2, 'quantity' => 2],
                    ['days_ago' => 20, 'product_index' => 1, 'quantity' => 1],
                    ['days_ago' => 5, 'product_index' => 0, 'quantity' => 1],
                ],
                'events' => [
                    ['event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS, 'entity_type' => AnalyticsEvent::ENTITY_ORDER, 'occurred_at' => $now->copy()->subDays(5), 'product_index' => 0],
                ],
            ],
            [
                'key' => 'risk',
                'name' => 'Growth Demo At Risk',
                'email' => 'growth-demo-risk+'.self::DEMO_KEY.'@example.com',
                'phone' => '01000000005',
                'city' => 'Tanta',
                'address' => '50 Demo Block',
                'session_id' => self::DEMO_KEY.'-risk-session',
                'orders' => [
                    ['days_ago' => 120, 'product_index' => 2, 'quantity' => 1],
                    ['days_ago' => 70, 'product_index' => 1, 'quantity' => 1],
                ],
                'events' => [],
            ],
            [
                'key' => 'vip',
                'name' => 'Growth Demo VIP',
                'email' => 'growth-demo-vip+'.self::DEMO_KEY.'@example.com',
                'phone' => '01000000006',
                'city' => 'Nasr City',
                'address' => '88 Demo Towers',
                'session_id' => self::DEMO_KEY.'-vip-session',
                'orders' => [
                    ['days_ago' => 160, 'product_index' => 0, 'quantity' => 1, 'unit_price' => 1499],
                    ['days_ago' => 120, 'product_index' => 0, 'quantity' => 1, 'unit_price' => 1499],
                    ['days_ago' => 90, 'product_index' => 0, 'quantity' => 1, 'unit_price' => 1499],
                    ['days_ago' => 60, 'product_index' => 1, 'quantity' => 2, 'unit_price' => 299],
                    ['days_ago' => 28, 'product_index' => 0, 'quantity' => 1, 'unit_price' => 1499],
                ],
                'events' => [
                    ['event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT, 'occurred_at' => $now->copy()->subDays(2), 'product_index' => 0],
                ],
            ],
        ];
    }
}
