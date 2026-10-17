<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthGuestCartSessionRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_survives_successful_password_login_session_rotation(): void
    {
        $user = User::factory()->create(['email' => 'login-cart@example.test']);
        $this->get(route('login'))->assertOk();
        $guestSessionId = session()->getId();
        $this->putGuestItemInSession($guestSessionId, 2);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($guestSessionId, session()->getId(), 'Login must still rotate the authenticated session.');
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'session_id' => null,
            'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'session_id' => $guestSessionId,
        ]);
    }

    public function test_guest_cart_survives_successful_registration_session_rotation(): void
    {
        $this->get(route('register'))->assertOk();
        $guestSessionId = session()->getId();
        $this->putGuestItemInSession($guestSessionId, 3);

        $this->post(route('register'), [
            'name' => 'Guest Cart New Customer',
            'email' => 'register-cart@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $user = User::query()->where('email', 'register-cart@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($guestSessionId, session()->getId(), 'Registration must still rotate the authenticated session.');
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'session_id' => null,
            'quantity' => 3,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'session_id' => $guestSessionId,
        ]);
    }

    private function putGuestItemInSession(string $sessionId, int $quantity): void
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Auth cart category',
            'slug' => 'auth-cart-'.Str::lower(Str::random(8)),
            'description' => 'Synthetic auth regression category',
            'meta_title' => 'Auth cart',
            'meta_keyword' => 'Auth cart',
            'meta_description' => 'Auth cart',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'category_id' => $categoryId,
            'name' => 'Guest cart item',
            'slug' => 'auth-cart-product-'.Str::lower(Str::random(8)),
            'base_price' => 100,
            'quantity' => 15,
            'status' => true,
            'has_variants' => false,
        ]);

        CartItem::query()->create([
            'user_id' => null,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => $quantity,
            'meta' => ['product_slug' => $product->slug],
        ]);
    }
}
