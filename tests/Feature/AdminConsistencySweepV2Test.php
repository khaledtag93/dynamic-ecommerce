<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminConsistencySweepV2Test extends TestCase
{
    public function test_remaining_admin_workspaces_use_shared_headers_and_stat_cards(): void
    {
        $headerPaths = [
            resource_path('views/admin/suppliers/index.blade.php'),
            resource_path('views/admin/coupons/index.blade.php'),
            resource_path('views/admin/promotions/index.blade.php'),
            resource_path('views/admin/imports/index.blade.php'),
            resource_path('views/admin/notifications/index.blade.php'),
        ];

        foreach ($headerPaths as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString('<x-admin.page-header', $source);
            $this->assertStringContainsString('<x-admin.stat-card', $source);
        }
    }

    public function test_coupon_and_promotion_rendering_use_valid_namespaces(): void
    {
        $couponSource = file_get_contents(resource_path('views/admin/coupons/_results.blade.php'));
        $promotionSource = file_get_contents(resource_path('views/admin/promotions/_results.blade.php'));

        $this->assertStringContainsString('\\Illuminate\\Support\\Str::limit', $couponSource);
        $this->assertStringContainsString('\\App\\Models\\Coupon::TYPE_PERCENT', $couponSource);
        $this->assertStringNotContainsString('IlluminateSupportStr::limit', $couponSource);
        $this->assertStringNotContainsString('AppModelsCoupon::TYPE_PERCENT', $couponSource);

        $this->assertStringContainsString('\\Illuminate\\Support\\Str::headline', $promotionSource);
        $this->assertStringNotContainsString('IlluminateSupportStr::headline', $promotionSource);
    }

    public function test_supplier_coupon_and_promotion_actions_are_rtl_aligned(): void
    {
        foreach ([
            resource_path('views/admin/suppliers/_results.blade.php'),
            resource_path('views/admin/coupons/_results.blade.php'),
            resource_path('views/admin/promotions/_results.blade.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString('rtl-text-start', $source);
            $this->assertStringContainsString('rtl-justify-start', $source);
        }
    }

    public function test_manual_summary_card_markup_is_removed_from_swept_pages(): void
    {
        foreach ([
            resource_path('views/admin/suppliers/index.blade.php'),
            resource_path('views/admin/coupons/index.blade.php'),
            resource_path('views/admin/promotions/index.blade.php'),
            resource_path('views/admin/imports/index.blade.php'),
            resource_path('views/admin/notifications/index.blade.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringNotContainsString('admin-card admin-stat-card h-100', $source);
        }
    }
}
