<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiscountWorkspaceClosureTest extends TestCase
{
    public function test_coupon_and_promotion_searches_are_bounded_and_escaped(): void
    {
        $coupon = file_get_contents(app_path('Http/Controllers/Admin/CouponController.php'));
        $promotion = file_get_contents(app_path('Http/Controllers/Admin/PromotionController.php'));

        foreach ([$coupon, $promotion] as $controller) {
            $this->assertStringContainsString(
                'mb_substr(trim((string) $request->string(\'search\')), 0, 100)',
                $controller
            );
            $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
            $this->assertStringNotContainsString('"%{$search}%"', $controller);
        }
    }

    public function test_coupon_list_and_editor_controls_have_explicit_labels(): void
    {
        $list = file_get_contents(resource_path('views/admin/coupons/index.blade.php'));
        $editor = file_get_contents(resource_path('views/admin/coupons/form.blade.php'));

        foreach ([
            'couponSearch',
            'couponTypeFilter',
            'couponStatusFilter',
            'couponUsageFilter',
            'couponPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $list);
            $this->assertStringContainsString('id="' . $controlId . '"', $list);
        }

        foreach ([
            'couponName',
            'couponCode',
            'couponType',
            'couponValue',
            'couponUsageLimit',
            'couponMinOrderAmount',
            'couponMaxDiscount',
            'isActive',
            'couponStartsAt',
            'couponEndsAt',
            'couponNotes',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $editor);
            $this->assertStringContainsString('id="' . $controlId . '"', $editor);
        }

        $this->assertMatchesRegularExpression(
            '/id="couponCode"[^>]*aria-required="true"/',
            $editor
        );
    }

    public function test_promotion_list_and_editor_controls_have_explicit_labels(): void
    {
        $list = file_get_contents(resource_path('views/admin/promotions/index.blade.php'));
        $editor = file_get_contents(resource_path('views/admin/promotions/create.blade.php'));

        foreach ([
            'promotionSearch',
            'promotionTypeFilter',
            'promotionStatusFilter',
            'promotionScheduleFilter',
            'promotionPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $list);
            $this->assertStringContainsString('id="' . $controlId . '"', $list);
        }

        foreach ([
            'promotionName',
            'promotionType',
            'promotionDiscountValue',
            'promotionCategory',
            'promotionMinSubtotal',
            'promotionBuyQuantity',
            'promotionGetQuantity',
            'promotionPriority',
            'promotionActive',
            'promotionStartsAt',
            'promotionEndsAt',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $editor);
            $this->assertStringContainsString('id="' . $controlId . '"', $editor);
        }

        $this->assertMatchesRegularExpression(
            '/id="promotionName"[^>]*aria-required="true"/',
            $editor
        );
    }
}
