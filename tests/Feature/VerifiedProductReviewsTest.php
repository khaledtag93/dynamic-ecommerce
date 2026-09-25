<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerifiedProductReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_review_without_a_verified_purchase(): void
    {
        $customer = User::factory()->create();
        $product = $this->makeProduct();

        $response = $this->actingAs($customer)->post(route('reviews.store', $product), [
            'rating' => 5,
            'comment' => 'I should not be allowed to publish this.',
        ]);

        $response->assertSessionHasErrors('review');
        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_verified_purchase_review_stays_pending_until_admin_approval(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $viewer = User::factory()->create();
        $product = $this->makeProduct();
        $this->makePaidOrder($customer, $product);

        $this->actingAs($customer)->post(route('reviews.store', $product), [
            'rating' => 5,
            'comment' => 'A real verified review body.',
        ])->assertRedirect(route('frontend.products.show', $product));

        $review = ProductReview::query()->firstOrFail();

        $this->assertTrue((bool) $review->verified);
        $this->assertSame(ProductReview::STATUS_PENDING, $review->status);

        $this->actingAs($viewer)
            ->get(route('frontend.products.show', $product))
            ->assertOk()
            ->assertDontSee('A real verified review body.');

        $admin = User::factory()->create(['role_as' => 1]);
        $operationsRole = Role::query()->where('slug', 'operations_manager')->firstOrFail();
        $admin->roles()->sync([$operationsRole->id]);

        $this->actingAs($admin)->patch(route('admin.reviews.moderate', $review), [
            'status' => ProductReview::STATUS_APPROVED,
        ])->assertRedirect();

        $review->refresh();

        $this->assertSame(ProductReview::STATUS_APPROVED, $review->status);
        $this->assertSame($admin->id, $review->moderated_by);
        $this->assertNotNull($review->moderated_at);

        $this->actingAs($viewer)
            ->get(route('frontend.products.show', $product))
            ->assertOk()
            ->assertSee('A real verified review body.')
            ->assertSee('Verified purchase');
    }

    public function test_editing_an_approved_review_returns_it_to_moderation(): void
    {
        $customer = User::factory()->create();
        $product = $this->makeProduct();
        $this->makePaidOrder($customer, $product);

        $review = ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'rating' => 4,
            'comment' => 'Original approved review.',
            'verified' => true,
            'status' => ProductReview::STATUS_APPROVED,
            'moderated_by' => User::factory()->create()->id,
            'moderated_at' => now(),
        ]);

        $this->actingAs($customer)->post(route('reviews.store', $product), [
            'rating' => 3,
            'comment' => 'Edited review awaiting moderation.',
        ])->assertRedirect(route('frontend.products.show', $product));

        $review->refresh();

        $this->assertSame(3, $review->rating);
        $this->assertSame('Edited review awaiting moderation.', $review->comment);
        $this->assertSame(ProductReview::STATUS_PENDING, $review->status);
        $this->assertNull($review->moderated_by);
        $this->assertNull($review->moderated_at);
    }

    public function test_admin_review_workspace_requires_review_moderation_permission(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $operationsAdmin = User::factory()->create(['role_as' => 1]);
        $operationsRole = Role::query()->where('slug', 'operations_manager')->firstOrFail();
        $operationsAdmin->roles()->sync([$operationsRole->id]);

        $this->actingAs($operationsAdmin)
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Product Reviews');

        $supportAdmin = User::factory()->create(['role_as' => 1]);
        $supportRole = Role::query()->where('slug', 'support_agent')->firstOrFail();
        $supportAdmin->roles()->sync([$supportRole->id]);

        $this->actingAs($supportAdmin)
            ->get(route('admin.reviews.index'))
            ->assertForbidden();
    }

    private function makeProduct(): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Review Category ' . Str::random(6),
            'slug' => 'review-category-' . Str::lower(Str::random(8)),
            'description' => 'Review test category',
            'meta_title' => 'Reviews',
            'meta_keyword' => 'reviews',
            'meta_description' => 'Reviews test category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Review Product ' . Str::random(6),
            'slug' => 'review-product-' . Str::lower(Str::random(8)),
            'sku' => 'REV-' . Str::upper(Str::random(6)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'cost_price' => 50,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);
    }

    private function makePaidOrder(User $customer, Product $product): Order
    {
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_STOREFRONT,
            'order_number' => 'REVIEW-' . Str::upper(Str::random(8)),
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'customer_name' => 'Review Customer',
            'customer_email' => 'review@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Review Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);

        return $order;
    }
}
