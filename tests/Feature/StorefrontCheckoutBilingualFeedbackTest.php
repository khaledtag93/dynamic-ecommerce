<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCheckoutBilingualFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_cart_redirect_uses_localized_arabic_feedback(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'سلة التسوق فارغة.');
    }

    public function test_checkout_success_flash_messages_are_translation_aware(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Frontend/CheckoutController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("->with('status', __('Your cart is empty.'))", $source);
        $this->assertStringContainsString("->with('success', __('Order placed successfully. Redirecting to secure payment...'))", $source);
        $this->assertStringContainsString("->with('success', __('Order placed successfully.'))", $source);
        $this->assertStringNotContainsString("->with('success', 'Order placed successfully.", $source);

        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('سلة التسوق فارغة.', $arabic['Your cart is empty.'] ?? null);
        $this->assertSame(
            'تم تسجيل الطلب بنجاح. جارٍ تحويلك إلى الدفع الآمن...',
            $arabic['Order placed successfully. Redirecting to secure payment...'] ?? null
        );
        $this->assertSame('تم تسجيل الطلب بنجاح.', $arabic['Order placed successfully.'] ?? null);
    }
}
