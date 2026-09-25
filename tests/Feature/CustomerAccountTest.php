<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_email_and_password_changes_require_current_password(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->get(route('account.index'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('account.index'))
            ->assertOk()->assertSee('Profile details')->assertSee(route('account.addresses.index'));

        $this->patch(route('account.profile.update'), [
            'name' => 'Changed Name', 'email' => $user->email,
        ])->assertRedirect();
        $this->assertSame('Changed Name', $user->fresh()->name);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->patch(route('account.profile.update'), [
            'name' => 'Changed Name', 'email' => 'new@example.test',
        ])->assertSessionHasErrors('current_password');
        $this->assertNotSame('new@example.test', $user->fresh()->email);

        $this->patch(route('account.profile.update'), [
            'name' => 'Changed Name', 'email' => 'new@example.test', 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);

        $this->patch(route('account.password.update'), [
            'current_password' => 'wrong', 'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password', null, 'passwordUpdate');

        $this->patch(route('account.password.update'), [
            'current_password' => 'password', 'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(0, (int) $user->fresh()->role_as);
    }


    public function test_profile_and_password_can_be_updated_through_live_account_endpoints(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.test',
            'email_verified_at' => now(),
        ]);

        $profile = $this->actingAs($user)->patchJson(route('account.profile.update'), [
            'name' => 'Live Account Name',
            'email' => 'live@example.test',
            'current_password' => 'password',
        ], ['X-Account-Live' => '1']);

        $profile->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('user.name', 'Live Account Name')
            ->assertJsonPath('user.email', 'live@example.test')
            ->assertJsonPath('email_verification_required', true);

        $this->assertSame('live@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);

        $password = $this->actingAs($user)->patchJson(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'new-live-password-123',
            'password_confirmation' => 'new-live-password-123',
        ], ['X-Account-Live' => '1']);

        $password->assertOk()->assertJsonPath('message', 'Password updated.');
        $this->assertTrue(Hash::check('new-live-password-123', $user->fresh()->password));
    }

    public function test_addresses_are_private_and_defaults_fall_back_after_deletion(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->post(route('account.addresses.store'), $this->address('Guest'))
            ->assertRedirect(route('login'));

        $this->actingAs($user)->post(route('account.addresses.store'), $this->address('Home'))
            ->assertRedirect(route('account.addresses.index'));
        $home = $user->addresses()->firstOrFail();
        $this->assertTrue($home->is_default_shipping);
        $this->assertTrue($home->is_default_billing);

        $this->post(route('account.addresses.store'), $this->address('Work', [
            'is_default_shipping' => '1',
        ]))->assertRedirect(route('account.addresses.index'));
        $work = $user->addresses()->where('label', 'Work')->firstOrFail();
        $this->assertTrue($work->is_default_shipping);
        $this->assertFalse($work->is_default_billing);
        $this->assertFalse($home->fresh()->is_default_shipping);
        $this->assertTrue($home->fresh()->is_default_billing);

        $foreign = $other->addresses()->create($this->address('Private'));
        $this->get(route('account.addresses.edit', $foreign))->assertNotFound();
        $this->patch(route('account.addresses.update', $foreign), $this->address('Stolen'))->assertNotFound();
        $this->delete(route('account.addresses.destroy', $foreign))->assertNotFound();
        $this->get(route('account.addresses.index'))->assertDontSee('Private');

        $this->patch(route('account.addresses.update', $work), $this->address('Office', [
            'is_default_shipping' => '1', 'is_default_billing' => '1',
        ]))->assertRedirect(route('account.addresses.index'));
        $this->assertTrue($work->fresh()->is_default_shipping);
        $this->assertTrue($work->fresh()->is_default_billing);
        $this->assertFalse($home->fresh()->is_default_billing);

        $this->deleteJson(route('account.addresses.destroy', $work), [], ['X-Address-Live' => '1'])
            ->assertOk()
            ->assertJsonPath('message', 'Address deleted.')
            ->assertJsonPath('addresses.0.id', $home->id)
            ->assertJsonPath('addresses.0.is_default_shipping', true)
            ->assertJsonPath('addresses.0.is_default_billing', true);
        $this->assertTrue($home->fresh()->is_default_shipping);
        $this->assertTrue($home->fresh()->is_default_billing);
        $this->assertDatabaseHas('customer_addresses', ['id' => $foreign->id, 'user_id' => $other->id]);
    }


    public function test_address_can_be_created_and_updated_through_live_endpoints(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user)->postJson(
            route('account.addresses.store'),
            $this->address('Live Home'),
            ['X-Address-Live' => '1']
        );

        $created->assertCreated()
            ->assertJsonPath('message', 'Address saved.')
            ->assertJsonPath('redirect_url', route('account.addresses.index'));

        $address = $user->addresses()->where('label', 'Live Home')->firstOrFail();

        $updated = $this->patchJson(
            route('account.addresses.update', $address),
            $this->address('Live Office', ['city' => 'Alexandria']),
            ['X-Address-Live' => '1']
        );

        $updated->assertOk()
            ->assertJsonPath('message', 'Address updated.')
            ->assertJsonPath('address.id', $address->id);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'label' => 'Live Office',
            'city' => 'Alexandria',
        ]);
    }

    public function test_saved_addresses_prefill_checkout_but_orders_keep_their_own_snapshot(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $shipping = $user->addresses()->create($this->address('Home', [
            'is_default_shipping' => true, 'address_line_1' => 'Original Shipping Street',
        ]));
        $billing = $user->addresses()->create($this->address('Billing', [
            'is_default_billing' => true, 'address_line_1' => 'Original Billing Street',
        ]));
        $foreign = $other->addresses()->create($this->address('Other Customer'));

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Account Checkout', 'slug' => 'account-checkout-'.Str::random(6),
            'description' => 'Test', 'meta_title' => 'Test', 'meta_keyword' => 'Test',
            'meta_description' => 'Test', 'status' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = Product::query()->create([
            'name' => 'Account Product', 'slug' => 'account-product-'.Str::random(6),
            'category_id' => $categoryId, 'base_price' => 100, 'quantity' => 3,
            'status' => true, 'has_variants' => false,
        ]);
        CartItem::query()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'unit_price' => 100, 'quantity' => 1, 'meta' => ['product_slug' => $product->slug],
        ]);

        $this->actingAs($user)->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('name="shipping_address_line_1" value="Original Shipping Street"', false)
            ->assertSee('name="billing_address_line_1" value="Original Billing Street"', false)
            ->assertSee('name="billing_same_as_shipping" value="0"', false)
            ->assertSee(route('checkout.index', ['address' => 'new']));
        $this->get(route('checkout.index', ['address' => $billing->id]))
            ->assertOk()->assertSee('name="shipping_address_line_1" value="Original Billing Street"', false);
        $this->get(route('checkout.index', ['address' => $foreign->id]))->assertNotFound();
        $this->get(route('checkout.index', ['address' => 'new']))
            ->assertOk()->assertSee('name="shipping_address_line_1" value=""', false);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Buyer', 'customer_email' => $user->email, 'customer_phone' => '01000000000',
            'shipping_address_line_1' => $shipping->address_line_1, 'shipping_city' => $shipping->city,
            'shipping_country' => $shipping->country, 'billing_same_as_shipping' => '0',
            'billing_address_line_1' => $billing->address_line_1, 'billing_city' => $billing->city,
            'billing_country' => $billing->country, 'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ])->assertSessionHasNoErrors();

        $order = $user->orders()->firstOrFail();
        $shipping->update(['address_line_1' => 'Changed Shipping Street']);
        $billing->delete();
        $this->assertSame('Original Shipping Street', $order->fresh()->shipping_address_line_1);
        $this->assertSame('Original Billing Street', $order->fresh()->billing_address_line_1);
        $this->assertFalse($order->fresh()->billing_same_as_shipping);
    }

    private function address(string $label, array $extra = []): array
    {
        return array_merge([
            'label' => $label,
            'recipient_name' => 'Customer',
            'phone' => '01000000000',
            'address_line_1' => 'Street',
            'city' => 'Cairo',
            'country' => 'Egypt',
        ], $extra);
    }
    public function test_account_navigation_exposes_current_page_semantics(): void
    {
        $navigation = file_get_contents(resource_path('views/frontend/account/partials/navigation.blade.php'));

        $this->assertSame(6, substr_count($navigation, 'aria-current="{{ request()->routeIs('));
        $this->assertStringContainsString("? 'page' : 'false' }}", $navigation);
    }

}
