<?php

namespace Tests\Feature;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponMoneyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_admin_rejects_money_over_precision(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->from(route('admin.coupons.create'))
            ->post(route('admin.coupons.store'), [
                'name' => 'Precision Guard',
                'code' => 'PRECISION-GUARD',
                'type' => Coupon::TYPE_FIXED,
                'value' => '10.001',
                'min_order_amount' => '20.001',
                'max_discount_amount' => '5.001',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors([
                'value',
                'min_order_amount',
                'max_discount_amount',
            ]);

        $this->assertDatabaseMissing('coupons', ['code' => 'PRECISION-GUARD']);
    }

    public function test_percentage_coupon_cannot_exceed_one_hundred_percent(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->from(route('admin.coupons.create'))
            ->post(route('admin.coupons.store'), [
                'name' => 'Too Large Percent',
                'code' => 'PERCENT-TOO-LARGE',
                'type' => Coupon::TYPE_PERCENT,
                'value' => '100.01',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('coupons', ['code' => 'PERCENT-TOO-LARGE']);
    }

    public function test_coupon_minimum_subtotal_uses_exact_cent_boundary(): void
    {
        $coupon = Coupon::query()->create([
            'name' => 'Exact Threshold',
            'code' => 'EXACT-THRESHOLD',
            'type' => Coupon::TYPE_FIXED,
            'value' => '0.05',
            'min_order_amount' => '0.30',
            'is_active' => true,
        ]);

        $this->assertTrue($coupon->meetsMinimumSubtotal(0.1 + 0.2));
        $this->assertSame(0.05, $coupon->calculateDiscount(0.1 + 0.2));

        $this->assertFalse($coupon->meetsMinimumSubtotal(0.29));
        $this->assertSame(0.0, $coupon->calculateDiscount(0.29));
    }

    public function test_percentage_coupon_rounds_half_up_to_exact_cents(): void
    {
        $coupon = Coupon::query()->create([
            'name' => 'Half Cent',
            'code' => 'HALF-CENT',
            'type' => Coupon::TYPE_PERCENT,
            'value' => '12.50',
            'is_active' => true,
        ]);

        $this->assertSame(0.03, $coupon->calculateDiscount(0.20));
    }

    public function test_coupon_cap_and_subtotal_clamp_are_exact(): void
    {
        $percentage = Coupon::query()->create([
            'name' => 'Capped Percent',
            'code' => 'CAPPED-PERCENT',
            'type' => Coupon::TYPE_PERCENT,
            'value' => '50.00',
            'max_discount_amount' => '0.03',
            'is_active' => true,
        ]);

        $fixed = Coupon::query()->create([
            'name' => 'Large Fixed',
            'code' => 'LARGE-FIXED',
            'type' => Coupon::TYPE_FIXED,
            'value' => '1.00',
            'is_active' => true,
        ]);

        $this->assertSame(0.03, $percentage->calculateDiscount(0.10));
        $this->assertSame(0.20, $fixed->calculateDiscount(0.20));
    }
}
