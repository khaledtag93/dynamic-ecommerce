<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminListSearchContractTest extends TestCase
{
    public function test_high_traffic_admin_search_controls_match_the_server_query_bound(): void
    {
        $controls = [
            'views/admin/orders/index.blade.php' => 'orderSearch',
            'views/admin/payments/index.blade.php' => 'paymentSearch',
            'views/admin/inventory/index.blade.php' => 'inventorySearch',
            'views/admin/purchases/index.blade.php' => 'purchaseSearch',
            'views/admin/returns/index.blade.php' => 'returnSearch',
            'views/admin/deliveries/index.blade.php' => 'deliverySearch',
            'views/admin/customers/index.blade.php' => 'customerSearch',
            'views/admin/suppliers/index.blade.php' => 'supplierSearch',
            'views/admin/coupons/index.blade.php' => 'couponSearch',
            'views/admin/promotions/index.blade.php' => 'promotionSearch',
            'views/admin/support/index.blade.php' => 'supportCaseSearch',
            'views/admin/reviews/index.blade.php' => 'reviewSearch',
        ];

        foreach ($controls as $view => $id) {
            $source = file_get_contents(resource_path($view));

            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($id, '/') . '"[^>]*maxlength="100"/',
                $source,
                $view . ' must expose the same 100-character search bound as its controller.'
            );
        }

        foreach ([
            'OrderController.php',
            'PaymentController.php',
            'InventoryController.php',
            'PurchaseController.php',
            'ReturnRequestController.php',
            'DeliveryController.php',
            'CustomerController.php',
            'SupplierController.php',
            'CouponController.php',
            'PromotionController.php',
            'SupportCaseController.php',
            'ProductReviewController.php',
        ] as $controller) {
            $source = file_get_contents(app_path('Http/Controllers/Admin/' . $controller));

            $this->assertStringContainsString(', 0, 100)', $source);
            $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $source);
        }
    }
}
